<?php

namespace App\Support;

use App\Models\Product;
use App\Services\RefundMoney;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Per-product availability, production, shipping, and packing.
 * Charge amounts are calculated in paise from stored product settings.
 * Posted prices, charge amounts, and eligibility flags are ignored.
 */
final class ProductFulfilment
{
    public const AVAILABILITY_READY = 'ready_stock';

    public const AVAILABILITY_MADE = 'made_to_order';

    public const AVAILABILITY_CONFIRM = 'confirm_with_team';

    public const UNIT_DAYS = 'days';

    public const UNIT_WEEKS = 'weeks';

    public const START_ORDER = 'order_confirmation';

    public const START_SPECIFICATION = 'final_specification_approval';

    public const CHARGE_INCLUDED = 'included';

    public const CHARGE_FIXED = 'fixed';

    public const CHARGE_QUOTED = 'quoted';

    public const BASIS_UNIT = 'per_unit';

    public const BASIS_LINE = 'per_line';

    public const MAX_AMOUNT = '999999.99';

    public const MAX_PERIOD = 520;

    public const TERMS_UPDATED = 'The previous unpaid order was cancelled because the product charges changed. Your cart has been kept.';

    public const QUOTE_SAVED = 'Your quotation request has been saved. Our team will confirm the charges below before payment. Your cart has been kept.';

    /** @var list<string> */
    public const AVAILABILITY = [
        self::AVAILABILITY_READY,
        self::AVAILABILITY_MADE,
        self::AVAILABILITY_CONFIRM,
    ];

    /** @var list<string> */
    public const UNITS = [self::UNIT_DAYS, self::UNIT_WEEKS];

    /** @var list<string> */
    public const STARTS = [self::START_ORDER, self::START_SPECIFICATION];

    /** @var list<string> */
    public const CHARGE_MODES = [self::CHARGE_INCLUDED, self::CHARGE_FIXED, self::CHARGE_QUOTED];

    /** @var list<string> */
    public const BASES = [self::BASIS_UNIT, self::BASIS_LINE];

    /** @var list<string> */
    public const CHARGE_FIELDS = [
        'shipping_india',
        'shipping_international',
        'packing_india',
        'packing_international',
    ];

    /**
     * Safe defaults for a product that has not been given an explicit promise.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'availability_mode' => self::AVAILABILITY_CONFIRM,
            'production_min' => null,
            'production_max' => null,
            'production_unit' => null,
            'production_starts' => null,
            'shipping_india_mode' => self::CHARGE_QUOTED,
            'shipping_india_amount' => null,
            'shipping_india_basis' => null,
            'shipping_international_mode' => self::CHARGE_QUOTED,
            'shipping_international_amount' => null,
            'shipping_international_basis' => null,
            'packing_india_mode' => self::CHARGE_QUOTED,
            'packing_india_amount' => null,
            'packing_india_basis' => null,
            'packing_international_mode' => self::CHARGE_QUOTED,
            'packing_international_amount' => null,
            'packing_international_basis' => null,
        ];
    }

    /**
     * Current Shop catalogue policy: ready stock, India shipping and packing
     * included, international charges quoted.
     *
     * @return array<string, mixed>
     */
    public static function currentShopPolicy(): array
    {
        return array_merge(self::defaults(), [
            'availability_mode' => self::AVAILABILITY_READY,
            'shipping_india_mode' => self::CHARGE_INCLUDED,
            'packing_india_mode' => self::CHARGE_INCLUDED,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributesFromRequest(Request $request, ?Product $existing): array
    {
        if (! $request->exists('availability_mode')) {
            return $existing ? [] : self::defaults();
        }

        $errors = [];
        $availability = (string) $request->input('availability_mode');
        if (! in_array($availability, self::AVAILABILITY, true)) {
            $errors['availability_mode'] = 'Choose ready stock, made to order, or confirm with team.';
        }

        $production = self::productionFromRequest($request, $availability === self::AVAILABILITY_MADE, $errors);
        $charges = [];
        foreach (self::CHARGE_FIELDS as $field) {
            $charges = array_merge($charges, self::chargeFromRequest($request, $field, $errors));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_merge([
            'availability_mode' => $availability,
            'needs_fulfilment_review' => $request->boolean('needs_fulfilment_review'),
            'fulfilment_review_note' => self::blankToNull($request->input('fulfilment_review_note')),
        ], $production, $charges);
    }

    public static function customerShippingNote(Product $product, ?string $context = null): string
    {
        if ($context === 'international') {
            return IndiaDelivery::ENQUIRY_HINT;
        }

        if ($context === 'quotation') {
            return IndiaDelivery::QUOTATION_SHIPPING_NOTE;
        }

        if ($context === 'studio' || ($context === null && $product->isStudioItem())) {
            if (self::indiaMode($product, 'shipping') === self::CHARGE_QUOTED
                && self::indiaMode($product, 'packing') === self::CHARGE_QUOTED) {
                return IndiaDelivery::STUDIO_SHIPPING_NOTE;
            }

            return self::indiaChargeSummary($product);
        }

        if ($context === 'india') {
            if (self::matchesIncludedIndia($product)) {
                return IndiaDelivery::SHOP_INDIA_SHIPPING;
            }

            return self::indiaChargeSummary($product);
        }

        if ($context === 'shop' || ($context === null && $product->isShopProduct())) {
            if (self::matchesIncludedIndia($product)) {
                return IndiaDelivery::SHOP_INDIA_SHIPPING.' '.IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING;
            }

            return self::indiaChargeSummary($product).' '.IndiaDelivery::SHOP_INTERNATIONAL_SHIPPING;
        }

        return IndiaDelivery::QUOTATION_SHIPPING_NOTE;
    }

    public static function availabilityLabel(Product $product): string
    {
        if ($product->availability_mode === self::AVAILABILITY_READY) {
            if ($product->hide_when_out_of_stock && (int) $product->stock <= 0) {
                return 'Ready stock is not currently available.';
            }

            return 'Ready stock.';
        }

        if ($product->availability_mode === self::AVAILABILITY_MADE) {
            return 'Made to order.';
        }

        return 'Availability is confirmed with the team.';
    }

    public static function productionSentence(Product $product): ?string
    {
        if ($product->availability_mode === self::AVAILABILITY_READY) {
            return null;
        }

        $min = $product->production_min;
        $max = $product->production_max;
        $unit = $product->production_unit;
        if ($min === null || $max === null || ! in_array($unit, self::UNITS, true)) {
            return null;
        }

        $range = (int) $min === (int) $max
            ? ((int) $min).' '.$unit
            : ((int) $min).'–'.((int) $max).' '.$unit;
        $start = match ($product->production_starts) {
            self::START_ORDER => ' It starts at order confirmation.',
            self::START_SPECIFICATION => ' It starts when the final specification is approved.',
            default => '',
        };

        return 'Production estimate: '.$range.'.'.$start.' This is production time, not dispatch or transit.';
    }

    /**
     * @return list<string>
     */
    public static function customerDetailLines(Product $product): array
    {
        $lines = [self::availabilityLabel($product)];
        $production = self::productionSentence($product);
        if ($production !== null) {
            $lines[] = $production;
        }

        return $lines;
    }

    public static function legacyLineVisible(string $line, ?Product $product): bool
    {
        if (! $product instanceof Product || ! filled($product->availability_mode)) {
            return true;
        }

        if (preg_match('/\b(5\s*(to|–|-)\s*12|15\s*(to|–|-)\s*35)\b/i', $line) === 1) {
            return false;
        }

        if ($product->availability_mode === self::AVAILABILITY_READY
            && preg_match('/production (lead )?time|estimated production|made to order|after your order is placed/i', $line) === 1) {
            return false;
        }

        if (self::indiaMode($product, 'shipping') === self::CHARGE_INCLUDED
            && preg_match('/not included in the displayed/i', $line) === 1) {
            return false;
        }

        if (self::indiaMode($product, 'shipping') === self::CHARGE_QUOTED
            && preg_match('/included in the displayed price/i', $line) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $items
     * @return array{
     *     payable: bool,
     *     india: bool,
     *     merchandise_paise: int,
     *     shipping_paise: int|null,
     *     packing_paise: int|null,
     *     reasons: list<string>,
     *     shipping_label: string,
     *     packing_label: string,
     *     customer_note: string,
     *     lines: list<array<string, mixed>>
     * }
     */
    public static function quote(iterable $items, bool $india): array
    {
        $merchandise = 0;
        $shipping = 0;
        $packing = 0;
        $shippingKnown = true;
        $packingKnown = true;
        $anyFixedShipping = false;
        $anyFixedPacking = false;
        $reasons = [];
        $lines = [];

        foreach ($items as $item) {
            $product = $item['product'] ?? null;
            if (! $product instanceof Product) {
                $shippingKnown = false;
                $packingKnown = false;
                $reasons[] = 'A cart item is missing its product settings.';
                continue;
            }

            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $lineTotal = self::paiseFromMoney($item['line_total'] ?? 0);
            if ($lineTotal === null) {
                $shippingKnown = false;
                $packingKnown = false;
                $reasons[] = $product->name.': the merchandise price could not be confirmed.';
                continue;
            }
            $merchandise += $lineTotal;

            $ship = self::component($product, 'shipping', $india, $quantity);
            $pack = self::component($product, 'packing', $india, $quantity);
            if ($ship['paise'] === null) {
                $shippingKnown = false;
                $reasons[] = $product->name.': shipping is quoted separately.';
            } else {
                $shipping += $ship['paise'];
                $anyFixedShipping = $anyFixedShipping || $ship['mode'] === self::CHARGE_FIXED;
            }
            if ($pack['paise'] === null) {
                $packingKnown = false;
                $reasons[] = $product->name.': packing is quoted separately.';
            } else {
                $packing += $pack['paise'];
                $anyFixedPacking = $anyFixedPacking || $pack['mode'] === self::CHARGE_FIXED;
            }

            $lines[] = [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'quantity' => $quantity,
                'size_label' => $item['size_label'] ?? null,
                'finish_slug' => $item['finish_slug'] ?? null,
                'availability_mode' => (string) $product->availability_mode,
                'availability_label' => self::availabilityLabel($product),
                'production_min' => $product->production_min,
                'production_max' => $product->production_max,
                'production_unit' => $product->production_unit,
                'production_starts' => $product->production_starts,
                'production_label' => self::productionSentence($product),
                'shipping_mode' => $ship['mode'],
                'shipping_amount' => $ship['amount'],
                'shipping_basis' => $ship['basis'],
                'shipping_basis_label' => self::basisLabel($ship['basis']),
                'shipping_charge' => $ship['paise'] === null ? null : RefundMoney::formatRupees($ship['paise']),
                'packing_mode' => $pack['mode'],
                'packing_amount' => $pack['amount'],
                'packing_basis' => $pack['basis'],
                'packing_basis_label' => self::basisLabel($pack['basis']),
                'packing_charge' => $pack['paise'] === null ? null : RefundMoney::formatRupees($pack['paise']),
            ];
        }

        if (! $india) {
            $reasons[] = 'International delivery is confirmed before payment.';
            $shippingKnown = false;
            $packingKnown = false;
        }

        $shippingPaise = $shippingKnown ? $shipping : null;
        $packingPaise = $packingKnown ? $packing : null;
        $payable = $india
            && $reasons === []
            && $shippingPaise !== null
            && $packingPaise !== null
            && $merchandise > 0
            && $lines !== [];

        return [
            'payable' => $payable,
            'india' => $india,
            'merchandise_paise' => $merchandise,
            'shipping_paise' => $shippingPaise,
            'packing_paise' => $packingPaise,
            'reasons' => array_values(array_unique($reasons)),
            'shipping_label' => self::chargeLabel('Shipping', $shippingPaise, $anyFixedShipping, 'Shipping included'),
            'packing_label' => self::chargeLabel('Packing', $packingPaise, $anyFixedPacking, 'Packing included'),
            'customer_note' => self::cartNote($lines, $reasons, $india),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public static function orderSnapshot(array $quote): array
    {
        $shipping = $quote['shipping_paise'];
        $packing = $quote['packing_paise'];
        $merchandise = (int) $quote['merchandise_paise'];
        $total = $merchandise + (int) $shipping + (int) $packing;

        return [
            'version' => 1,
            'destination' => 'india',
            'customer_note' => (string) $quote['customer_note'],
            'shipping_label' => (string) $quote['shipping_label'],
            'packing_label' => (string) $quote['packing_label'],
            'merchandise' => RefundMoney::formatRupees($merchandise),
            'shipping' => RefundMoney::formatRupees((int) $shipping),
            'packing' => RefundMoney::formatRupees((int) $packing),
            'total' => RefundMoney::formatRupees($total),
            'lines' => $quote['lines'],
        ];
    }

    public static function basisLabel(?string $basis): ?string
    {
        return match ($basis) {
            self::BASIS_UNIT => 'per unit',
            self::BASIS_LINE => 'per product line',
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private static function productionFromRequest(Request $request, bool $required, array &$errors): array
    {
        $min = self::blankToNull($request->input('production_min'));
        $max = self::blankToNull($request->input('production_max'));
        $unit = self::blankToNull($request->input('production_unit'));
        $starts = self::blankToNull($request->input('production_starts'));
        $any = $min !== null || $max !== null || $unit !== null || $starts !== null;

        if ($required && ! $any) {
            $errors['production_min'] = 'Enter the production estimate for a made-to-order product.';

            return [
                'production_min' => null,
                'production_max' => null,
                'production_unit' => null,
                'production_starts' => null,
            ];
        }

        if (! $required && ! $any) {
            return [
                'production_min' => null,
                'production_max' => null,
                'production_unit' => null,
                'production_starts' => null,
            ];
        }

        if (! is_numeric($min) || (int) $min < 1 || (int) $min > self::MAX_PERIOD || (string) (int) $min !== (string) $min) {
            $errors['production_min'] = 'Enter a production minimum from 1 to '.self::MAX_PERIOD.'.';
        }
        if (! is_numeric($max) || (int) $max < 1 || (int) $max > self::MAX_PERIOD || (string) (int) $max !== (string) $max) {
            $errors['production_max'] = 'Enter a production maximum from 1 to '.self::MAX_PERIOD.'.';
        }
        if (is_numeric($min) && is_numeric($max) && (int) $min > (int) $max) {
            $errors['production_max'] = 'The production maximum cannot be less than the minimum.';
        }
        if (! in_array($unit, self::UNITS, true)) {
            $errors['production_unit'] = 'Choose days or weeks.';
        }
        if (! in_array($starts, self::STARTS, true)) {
            $errors['production_starts'] = 'Choose whether production starts at order confirmation or final-specification approval.';
        }

        return [
            'production_min' => is_numeric($min) ? (int) $min : null,
            'production_max' => is_numeric($max) ? (int) $max : null,
            'production_unit' => $unit,
            'production_starts' => $starts,
        ];
    }

    /**
     * @param  array<string, string>  $errors
     * @return array<string, mixed>
     */
    private static function chargeFromRequest(Request $request, string $field, array &$errors): array
    {
        $modeKey = $field.'_mode';
        $amountKey = $field.'_amount';
        $basisKey = $field.'_basis';
        $mode = (string) $request->input($modeKey);
        if (! in_array($mode, self::CHARGE_MODES, true)) {
            $errors[$modeKey] = 'Choose included in price, fixed charge, or quoted separately.';

            return [$modeKey => null, $amountKey => null, $basisKey => null];
        }

        if ($mode !== self::CHARGE_FIXED) {
            return [$modeKey => $mode, $amountKey => null, $basisKey => null];
        }

        $raw = trim((string) $request->input($amountKey));
        $basis = (string) $request->input($basisKey);
        if ($raw === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
            $errors[$amountKey] = 'Enter a fixed charge of 0 or more, with at most two decimal places.';
        } else {
            $normalized = self::normalizeDecimal($raw);
            if ($normalized === null || strcmp($normalized, self::MAX_AMOUNT) > 0) {
                $errors[$amountKey] = 'Enter a fixed charge from 0.00 to '.self::MAX_AMOUNT.'.';
            }
        }
        if (! in_array($basis, self::BASES, true)) {
            $errors[$basisKey] = 'Choose per unit or per product line.';
        }

        return [
            $modeKey => $mode,
            $amountKey => isset($normalized) ? $normalized : null,
            $basisKey => in_array($basis, self::BASES, true) ? $basis : null,
        ];
    }

    /**
     * @return array{mode: string|null, amount: string|null, basis: string|null, paise: int|null}
     */
    private static function component(Product $product, string $kind, bool $india, int $quantity): array
    {
        $prefix = $kind.'_'.($india ? 'india' : 'international');
        $mode = $product->{$prefix.'_mode'};
        if ($mode === self::CHARGE_INCLUDED) {
            return ['mode' => $mode, 'amount' => null, 'basis' => null, 'paise' => 0];
        }
        if ($mode !== self::CHARGE_FIXED) {
            return ['mode' => is_string($mode) ? $mode : null, 'amount' => null, 'basis' => null, 'paise' => null];
        }

        $amount = self::normalizeDecimal((string) $product->{$prefix.'_amount'});
        $basis = $product->{$prefix.'_basis'};
        if ($amount === null || ! in_array($basis, self::BASES, true)) {
            return ['mode' => $mode, 'amount' => null, 'basis' => null, 'paise' => null];
        }

        $unitPaise = self::paiseFromMoney($amount);
        if ($unitPaise === null) {
            return ['mode' => $mode, 'amount' => $amount, 'basis' => $basis, 'paise' => null];
        }

        if ($basis === self::BASIS_LINE) {
            return ['mode' => $mode, 'amount' => $amount, 'basis' => $basis, 'paise' => $unitPaise];
        }

        if ($quantity < 1 || $unitPaise > intdiv(PHP_INT_MAX, $quantity)) {
            return ['mode' => $mode, 'amount' => $amount, 'basis' => $basis, 'paise' => null];
        }

        return ['mode' => $mode, 'amount' => $amount, 'basis' => $basis, 'paise' => $unitPaise * $quantity];
    }

    private static function chargeLabel(string $name, ?int $paise, bool $anyFixed, string $includedLabel): string
    {
        if ($paise === null) {
            return 'Quoted separately';
        }

        if (! $anyFixed) {
            return $includedLabel;
        }

        return '₹'.RefundMoney::formatRupees($paise);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<string>  $reasons
     */
    private static function cartNote(array $lines, array $reasons, bool $india): string
    {
        if (! $india) {
            return IndiaDelivery::ENQUIRY_HINT;
        }

        if ($reasons !== []) {
            return 'These charges need confirmation before payment: '.implode(' ', $reasons);
        }

        if ($lines !== [] && array_reduce($lines, function (bool $carry, array $line): bool {
            return $carry
                && $line['shipping_mode'] === self::CHARGE_INCLUDED
                && $line['packing_mode'] === self::CHARGE_INCLUDED;
        }, true)) {
            return IndiaDelivery::SHOP_INDIA_SHIPPING;
        }

        return 'Shipping and packing use the fixed charges saved for each product.';
    }

    private static function matchesIncludedIndia(Product $product): bool
    {
        return self::indiaMode($product, 'shipping') === self::CHARGE_INCLUDED
            && self::indiaMode($product, 'packing') === self::CHARGE_INCLUDED;
    }

    private static function indiaChargeSummary(Product $product): string
    {
        return self::componentSentence('Shipping within India', $product, 'shipping')
            .' '.self::componentSentence('Packing within India', $product, 'packing');
    }

    private static function componentSentence(string $label, Product $product, string $kind): string
    {
        $mode = self::indiaMode($product, $kind);
        if ($mode === self::CHARGE_INCLUDED) {
            return $label.' is included in the displayed price.';
        }
        if ($mode === self::CHARGE_FIXED) {
            $amount = self::normalizeDecimal((string) $product->{$kind.'_india_amount'});
            $basis = self::basisLabel((string) $product->{$kind.'_india_basis'});
            if ($amount === null || $basis === null) {
                return $label.' is quoted separately.';
            }

            return $label.' is a fixed charge of ₹'.$amount.' '.$basis.'.';
        }

        return $label.' is quoted separately.';
    }

    private static function indiaMode(Product $product, string $kind): ?string
    {
        $mode = $product->{$kind.'_india_mode'};

        return is_string($mode) ? $mode : null;
    }

    private static function paiseFromMoney(mixed $amount): ?int
    {
        $normalized = self::normalizeDecimal(is_string($amount) ? $amount : number_format((float) $amount, 2, '.', ''));
        if ($normalized === null) {
            return null;
        }

        try {
            return RefundMoney::paiseFromDecimal($normalized);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function normalizeDecimal(string $amount): ?string
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '00');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $normalized = ltrim($whole, '0');
        if ($normalized === '') {
            $normalized = '0';
        }

        return $normalized.'.'.$fraction;
    }

    private static function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
