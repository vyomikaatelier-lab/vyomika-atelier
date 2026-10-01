<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;

/**
 * India self-service checkout includes shipping in the product price.
 * Any other destination is an enquiry until staff confirm shipping.
 * Posted prices, totals, and eligibility flags are never consulted.
 */
final class IndiaDelivery
{
    public const SHOP_INDIA_SHIPPING = 'Shipping within India is included in the displayed price.';

    public const SHOP_INTERNATIONAL_SHIPPING = 'For international delivery, shipping charges and estimated delivery time are confirmed before payment.';

    public const STUDIO_SHIPPING_NOTE = 'Shipping and packing are quoted separately at dispatch and agreed with the client before dispatch.';

    public const QUOTATION_SHIPPING_NOTE = 'Shipping terms and timelines are confirmed in the quotation.';

    public const TIMELINE_CONFIRMATION = 'Production and delivery timelines depend on the product and destination. Please refer to the product’s shipping information; our team will confirm the applicable timeline before payment.';

    public const CUSTOMER_NOTE = self::SHOP_INDIA_SHIPPING;

    public const ENQUIRY_SAVED = 'Your international delivery enquiry has been saved. Our team will confirm shipping charges and estimated delivery time before payment.';

    public const PAYMENT_BLOCKED = 'International delivery is not available for online payment. Submit a shipping enquiry and our team will confirm shipping charges and estimated delivery time before payment.';

    public const STALE_ORDER_REFRESH = 'This order includes an older shipping charge, so online payment cannot be started. Please submit a new checkout. Shipping is included within India.';

    public const STALE_ORDER_RETIRED = 'An older order that included a separate shipping charge has been cancelled. Please place a new order. Shipping is included within India.';

    public const STALE_SHIPPING_SUPPORT = 'This order includes an older shipping charge, so online payment cannot be started. Please contact the studio for help. Saved payment details have not been changed.';

    public const SHIPPING_INCLUDED_INDIA = 'Shipping included within India';

    public const SHIPPING_QUOTED = 'Shipping quoted separately';

    public const MERCHANDISE_SUBTOTAL = 'Merchandise subtotal';

    public const ENQUIRY_HINT = 'Shipping quoted separately. Our team will confirm shipping charges, estimated delivery time, and import-duty responsibility before payment.';

    public static function shippingTrustNote(?Product $product): string
    {
        if ($product?->isStudioItem()) {
            return self::STUDIO_SHIPPING_NOTE;
        }

        if ($product?->isShopProduct()) {
            return self::SHOP_INDIA_SHIPPING.' '.self::SHOP_INTERNATIONAL_SHIPPING;
        }

        return self::QUOTATION_SHIPPING_NOTE;
    }

    public static function shippingNoteFor(?Product $product, ?string $context = null): string
    {
        return match ($context) {
            'studio' => self::STUDIO_SHIPPING_NOTE,
            'shop' => self::SHOP_INDIA_SHIPPING.' '.self::SHOP_INTERNATIONAL_SHIPPING,
            'india' => self::SHOP_INDIA_SHIPPING,
            'international' => self::ENQUIRY_HINT,
            'quotation' => self::QUOTATION_SHIPPING_NOTE,
            default => self::shippingTrustNote($product),
        };
    }

    public static function packagingFilmSentence(?Product $product, ?string $shippingContext = null): string
    {
        $isShop = ($shippingContext === 'shop' || $shippingContext === 'india')
            || ($shippingContext === null && ($product?->isShopProduct() ?? false));

        return $isShop
            ? 'PVD surfaces are film-wrapped to prevent scratches during Pan-India shipping.'
            : 'PVD surfaces are film-wrapped to prevent scratches in transit.';
    }

    public static function qualifiedCareDeliveryLine(string $line, ?Product $product): string
    {
        if ($product === null || $product->isShopProduct() || ! str_contains($line, 'Pan-India shipping')) {
            return $line;
        }

        if ($product->isStudioItem()) {
            return 'Shipping and packing: quoted separately at dispatch and agreed before dispatch.';
        }

        return 'Delivery: available Pan-India from our Delhi studio. '.self::QUOTATION_SHIPPING_NOTE;
    }

    public static function isIndia(?string $country): bool
    {
        return strcasecmp(trim((string) $country), 'India') === 0;
    }

    public static function hasObsoleteShippingCharge(Order $order): bool
    {
        return (float) $order->shipping_cost > 0;
    }

    public static function canInitiateSelfServicePayment(Order $order): bool
    {
        return self::isIndia($order->country) && ! self::hasObsoleteShippingCharge($order);
    }
}
