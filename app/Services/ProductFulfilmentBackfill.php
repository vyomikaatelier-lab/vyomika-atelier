<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFulfilmentOriginal;
use App\Support\ProductFulfilment;
use Illuminate\Support\Facades\DB;

class ProductFulfilmentBackfill
{
    /**
     * Snapshot each product once, then apply section defaults.
     * A later run does not overwrite an admin edit made after the snapshot.
     *
     * @return array{shop: int, studio: int, unknown: int, flagged: int, skipped: int}
     */
    public function run(): array
    {
        $counts = [
            'shop' => 0,
            'studio' => 0,
            'unknown' => 0,
            'flagged' => 0,
            'skipped' => 0,
        ];

        foreach (Product::query()->orderBy('id')->pluck('id') as $productId) {
            $outcome = DB::transaction(function () use ($productId) {
                $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
                if (! $product) {
                    return 'skipped';
                }

                if (ProductFulfilmentOriginal::query()->where('product_id', $product->id)->exists()) {
                    return 'skipped';
                }

                $section = in_array($product->section, Product::SECTIONS, true)
                    ? (string) $product->section
                    : 'unknown';
                $decision = $this->decision($product, $section);
                $original = $this->originalPayload($product);

                ProductFulfilmentOriginal::query()->create([
                    'product_id' => $product->id,
                    'original' => $original,
                    'applied' => $decision['applied'],
                    'applied_at' => now(),
                ]);

                $product->forceFill($decision['attributes'])->save();

                return $decision['bucket'];
            });

            if ($outcome === 'skipped') {
                $counts['skipped']++;
                continue;
            }

            $counts[$outcome['section']]++;
            if ($outcome['flagged']) {
                $counts['flagged']++;
            }
        }

        return $counts;
    }

    /**
     * Remove ready-stock review notes that only asked for a production estimate.
     * Stock, saved text, and original snapshots are left unchanged.
     *
     * @return array{cleared: int, trimmed: int, kept: int}
     */
    public function clearResolvedReadyStockFlags(): array
    {
        $counts = ['cleared' => 0, 'trimmed' => 0, 'kept' => 0];

        $ids = Product::query()
            ->where('section', Product::SECTION_SHOP)
            ->where('availability_mode', ProductFulfilment::AVAILABILITY_READY)
            ->where('needs_fulfilment_review', true)
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $productId) {
            $outcome = DB::transaction(function () use ($productId) {
                $product = Product::query()->whereKey($productId)->lockForUpdate()->first();
                if (! $product
                    || $product->section !== Product::SECTION_SHOP
                    || $product->availability_mode !== ProductFulfilment::AVAILABILITY_READY
                    || ! $product->needs_fulfilment_review) {
                    return 'kept';
                }

                $stock = $product->stock;
                $hide = (bool) $product->hide_when_out_of_stock;
                $text = $product->tab_shipping;
                $remaining = self::withoutResolvedReadyStockNotes((string) $product->fulfilment_review_note);
                if ($remaining === (string) $product->fulfilment_review_note) {
                    return 'kept';
                }

                $product->forceFill([
                    'fulfilment_review_note' => $remaining === '' ? null : $remaining,
                    'needs_fulfilment_review' => $remaining !== '',
                ])->save();

                $product->refresh();
                if ((int) $product->stock !== (int) $stock
                    || (bool) $product->hide_when_out_of_stock !== $hide
                    || (string) $product->tab_shipping !== (string) $text) {
                    throw new \RuntimeException('Clearing a ready-stock review flag changed product data.');
                }

                return $remaining === '' ? 'cleared' : 'trimmed';
            });

            $counts[$outcome]++;
        }

        return $counts;
    }

    public static function withoutResolvedReadyStockNotes(string $note): string
    {
        $resolved = [
            'A production range is saved, but the text does not say whether it starts at order confirmation or final-specification approval. Ready stock was applied. Complete the structured production fields only if this product is not ready stock.',
            'The saved text says the production lead time will be confirmed after the order is placed. Enter the estimate and its start event before showing a production period.',
        ];
        $lines = preg_split("/\r\n|\n|\r/", $note) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || in_array($line, $resolved, true)) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * @return array{attributes: array<string, mixed>, applied: array<string, mixed>, bucket: array{section: string, flagged: bool}}
     */
    private function decision(Product $product, string $section): array
    {
        $text = (string) $product->tab_shipping;
        $notes = [];
        $attributes = ProductFulfilment::defaults();

        if ($section === Product::SECTION_SHOP) {
            $attributes = array_merge($attributes, ProductFulfilment::currentShopPolicy());
            $this->flagShopText($text, $notes);
        } elseif ($section === Product::SECTION_STUDIO) {
            $mapped = $this->mapStudioProduction($text);
            if ($mapped !== null) {
                $attributes = array_merge($attributes, $mapped);
            } elseif ($this->mentionsProduction($text)) {
                $notes[] = 'The saved production text could not be mapped to a minimum, maximum, unit, and start event. Complete it in the product form.';
            }
        } else {
            if (preg_match('/included in the displayed price|5\s*(to|–|-)\s*12|15\s*(to|–|-)\s*35/i', $text) === 1) {
                $notes[] = 'This product is not classified as Shop. Review the saved shipping text before publishing a promise.';
            }
        }

        $note = $notes === [] ? null : implode("\n", $notes);
        $attributes['fulfilment_review_note'] = $note;
        $attributes['needs_fulfilment_review'] = $note !== null;

        return [
            'attributes' => $attributes,
            'applied' => [
                'section_treated_as' => $section,
                'needs_fulfilment_review' => $note !== null,
                'review_note' => $note,
            ],
            'bucket' => [
                'section' => $section === Product::SECTION_SHOP || $section === Product::SECTION_STUDIO ? $section : 'unknown',
                'flagged' => $note !== null,
            ],
        ];
    }

    /**
     * @param  list<string>  $notes
     */
    private function flagShopText(string $text, array &$notes): void
    {
        if (preg_match('/\b(5\s*(to|–|-)\s*12|15\s*(to|–|-)\s*35)\b/i', $text) === 1) {
            $notes[] = 'The saved text contains a generic delivery window. It was not copied into the structured fields.';
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapStudioProduction(string $text): ?array
    {
        if (preg_match('/(\d+)\s*[–\-]\s*(\d+)\s*weeks after approval of final/iu', $text, $matches) !== 1) {
            return null;
        }

        $min = (int) $matches[1];
        $max = (int) $matches[2];
        if ($min < 1 || $max < $min || $max > ProductFulfilment::MAX_PERIOD) {
            return null;
        }

        return [
            'availability_mode' => ProductFulfilment::AVAILABILITY_MADE,
            'production_min' => $min,
            'production_max' => $max,
            'production_unit' => ProductFulfilment::UNIT_WEEKS,
            'production_starts' => ProductFulfilment::START_SPECIFICATION,
        ];
    }

    private function mentionsProduction(string $text): bool
    {
        return preg_match('/production|weeks|business days/i', $text) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function originalPayload(Product $product): array
    {
        return [
            'section' => $product->section,
            'stock' => $product->stock,
            'hide_when_out_of_stock' => (bool) $product->hide_when_out_of_stock,
            'purchase_mode' => $product->purchase_mode,
            'pricing_type' => $product->pricing_type,
            'is_active' => (bool) $product->is_active,
            'headline_text' => $product->headline_text,
            'tab_shipping' => $product->tab_shipping,
            'tab_packaging' => $product->tab_packaging,
            'availability_mode' => $product->availability_mode,
            'production_min' => $product->production_min,
            'production_max' => $product->production_max,
            'production_unit' => $product->production_unit,
            'production_starts' => $product->production_starts,
            'shipping_india_mode' => $product->shipping_india_mode,
            'packing_india_mode' => $product->packing_india_mode,
            'shipping_international_mode' => $product->shipping_international_mode,
            'packing_international_mode' => $product->packing_international_mode,
        ];
    }
}
