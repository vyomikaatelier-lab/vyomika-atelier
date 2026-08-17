@extends('layouts.admin')

@section('title', isset($product) ? 'Edit Product' : 'Add Product')

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($product) ? 'Edit' : 'Add' }} Product</h1>

@php
    use App\Models\Product;

    $currentSection = old('section', isset($product) ? $product->resolvedSection() : '');
    $currentPurchaseMode = old('purchase_mode', isset($product) ? $product->resolvedPurchaseMode() : (Product::SECTION_PURCHASE_MODE_MAP[$currentSection] ?? ''));
    $currentPricingType = old('pricing_type', isset($product) ? $product->resolvedPricingType() : '');
    $sectionLabels = ['shop' => 'Shop', 'studio' => 'Studio', 'railings' => 'Railings'];
    $purchaseModeLabels = ['checkout' => 'Checkout', 'enquiry' => 'Enquiry', 'quote' => 'Quote'];
    $pricingTypeLabels = ['fixed' => 'Fixed price', 'square_foot' => 'Per sq ft', 'quotation_only' => 'Quotation only'];
    $selectedCategoryId = old('category_id', isset($product) ? $product->category_id : null);
    $selectedCategorySlug = $categories->firstWhere('id', (int) $selectedCategoryId)?->slug
        ?? (isset($product) ? $product->category?->slug : null);
    $showDoorHandleSizes = $selectedCategorySlug === 'door-handles';
    $showMirrorSizes = $selectedCategorySlug === 'mirror-frames';
    $showMirrorDimensions = false;
@endphp

@php
    $returnCategoryId = request('category_id', old('_return_category_id', isset($product) ? $product->category_id : null));
    $returnSection = request('section', old('_return_section'));
    $returnFilter = request('filter', old('_return_filter'));
    $returnSearch = request('q', old('_return_q'));
@endphp

@if(request('saved') || session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm max-w-2xl">{{ session('success') ?: 'Product saved successfully.' }}</div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}" class="bg-white p-6 rounded-lg shadow max-w-2xl space-y-4">
    @csrf
    @if(isset($product)) @method('PUT') @endif
    <input type="hidden" name="_page_save" value="1">
    @if(filled($returnCategoryId))
    <input type="hidden" name="_return_category_id" value="{{ $returnCategoryId }}">
    @endif
    @if(filled($returnSection))
    <input type="hidden" name="_return_section" value="{{ $returnSection }}">
    @endif
    @if(filled($returnFilter))
    <input type="hidden" name="_return_filter" value="{{ $returnFilter }}">
    @endif
    @if(filled($returnSearch))
    <input type="hidden" name="_return_q" value="{{ $returnSearch }}">
    @endif

    <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm">
        <p class="text-gray-600">Section decides storefront behaviour: <strong>Shop</strong> → Checkout (cart/order), <strong>Studio</strong> → Enquiry (custom order, no cart), <strong>Railings</strong> → Quote (project quotation only, never enters cart).</p>
    </div>

    <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" placeholder="Product Name" required class="w-full border px-3 py-2 rounded">
    <input type="text" name="slug" value="{{ old('slug', $product->slug ?? '') }}" placeholder="Slug (optional — auto from name)" class="w-full border px-3 py-2 rounded">

    <section class="rounded-lg border-2 border-gray-300 bg-gray-50 p-4 space-y-4" aria-labelledby="pdp-content-heading">
        <div>
            <h2 id="pdp-content-heading" class="font-semibold text-base">As shown on product page</h2>
            <p class="text-sm text-gray-600 mt-1">Edit the copy visitors see on the public product detail page — in the same order as on the website.</p>
        </div>

        <div>
            <label for="headline_text" class="text-sm font-medium text-gray-800 block mb-1">Line under title <span class="font-normal text-gray-500">(optional)</span></label>
            <p class="text-xs text-gray-500 mb-2">Small grey line below the product name, e.g. <em>SKU: MF-001 · Pan-India shipping</em>. Leave blank to auto-build from SKU only; hidden on the site when both this field and SKU are empty.</p>
            <input id="headline_text" type="text" name="headline_text" value="{{ old('headline_text', $product->headline_text ?? '') }}" placeholder="e.g. SKU: MF-001 · Pan-India shipping" class="w-full border px-3 py-2 rounded bg-white">
            @error('headline_text')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="description" class="text-sm font-medium text-gray-800 block mb-1">Main description</label>
            <p class="text-xs text-gray-500 mb-2">Shown in the Description tab on the product page. HTML is allowed.</p>
            <textarea id="description" name="description" rows="5" placeholder="Product description shown on the storefront" class="w-full border px-3 py-2 rounded bg-white">{{ old('description', $product->description ?? '') }}</textarea>
            @error('description')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="swatches_note" class="text-sm font-medium text-gray-800 block mb-1">Note below PVD finish swatches <span class="font-normal text-gray-500">(optional)</span></label>
            <p class="text-xs text-gray-500 mb-2">Shown under the finish colour swatches. Leave blank for the default black-finish +30% note.</p>
            <input id="swatches_note" type="text" name="swatches_note" value="{{ old('swatches_note', $product->swatches_note ?? '') }}" placeholder="Black Mirror &amp; Black Brush: +30% on sq ft rate" class="w-full border px-3 py-2 rounded bg-white">
            @error('swatches_note')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>

        @include('admin.partials.mirror-size-options', ['showMirrorSizes' => $showMirrorSizes, 'product' => $product ?? null])

        <fieldset class="space-y-3 border-t border-gray-200 pt-4">
            <legend class="text-sm font-medium text-gray-800 px-1">Tabs below the buy area</legend>
            <p class="text-xs text-gray-500 -mt-1 mb-2">Specifications, Packaging, and Shipping tabs. Leave blank to use built-in defaults.</p>
            <div>
                <label for="tab_specifications" class="text-sm font-medium text-gray-800 block mb-1">Specifications tab</label>
                <p class="text-xs text-gray-500 mb-2">One specification per line. Shown as a bullet list on the product page (same style as the site). Leave blank for built-in defaults.</p>
                @php
                    $specificationsValue = old('tab_specifications', isset($product) ? implode("\n", $product->specificationLines()) : '');
                @endphp
                <textarea id="tab_specifications" name="tab_specifications" rows="8" placeholder="Material: Grade 304/316 stainless with PVD coating&#10;Finish options: Gold, Rose Gold, Champagne, Black&#10;Lead time: 3–4 weeks — Pan-India from Delhi studio" class="w-full border px-3 py-2 rounded text-sm bg-white leading-relaxed">{{ $specificationsValue }}</textarea>
                @error('tab_specifications')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tab_packaging" class="text-sm font-medium text-gray-800 block mb-1">Packaging tab</label>
                <p class="text-xs text-gray-500 mb-2">One point per line — shown as a bullet list on the product page (same style as Specifications). Leave blank for built-in defaults.</p>
                @php
                    $packagingValue = old('tab_packaging', isset($product) ? implode("\n", \App\Models\Product::linesFromTabText($product->tab_packaging)) : '');
                @endphp
                <textarea id="tab_packaging" name="tab_packaging" rows="6" placeholder="Protective foam and corner guards&#10;Plywood crate for Pan-India transit&#10;Film-wrapped PVD surfaces" class="w-full border px-3 py-2 rounded text-sm bg-white leading-relaxed">{{ $packagingValue }}</textarea>
                @error('tab_packaging')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tab_shipping" class="text-sm font-medium text-gray-800 block mb-1">Shipping tab</label>
                <p class="text-xs text-gray-500 mb-2">One point per line — shown as a bullet list on the product page. Leave blank for built-in defaults.</p>
                @php
                    $shippingValue = old('tab_shipping', isset($product) ? implode("\n", \App\Models\Product::linesFromTabText($product->tab_shipping)) : '');
                @endphp
                <textarea id="tab_shipping" name="tab_shipping" rows="6" placeholder="Lead time: 3–4 weeks from order confirmation&#10;Metro cities: door delivery&#10;Pan-India courier or freight" class="w-full border px-3 py-2 rounded text-sm bg-white leading-relaxed">{{ $shippingValue }}</textarea>
                @error('tab_shipping')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            </div>
        </fieldset>
    </section>

    <details class="border rounded p-3 bg-gray-50" open>
        <summary class="font-medium cursor-pointer text-sm">SEO &amp; search</summary>
        @php
            $seoTitle = old('meta_title', $product->meta_title ?? '');
            $seoDesc = old('meta_description', $product->meta_description ?? '');
            $previewTitle = $seoTitle !== '' ? $seoTitle : (old('name', $product->name ?? '').' — Vyomika Atelier');
            $previewUrl = url('/shop/'.old('slug', $product->slug ?? 'your-slug'));
            $seoWarnings = [];
            if (blank(old('image', $product->image ?? ''))) { $seoWarnings[] = 'Primary image missing'; }
            if (blank(old('description', $product->description ?? ''))) { $seoWarnings[] = 'Description missing'; }
            if (blank(old('sku', $product->sku ?? ''))) { $seoWarnings[] = 'SKU missing'; }
            if (blank(old('category_id', $product->category_id ?? ''))) { $seoWarnings[] = 'Category missing'; }
            if (blank(old('image_alt', $product->image_alt ?? ''))) { $seoWarnings[] = 'Image alt text missing (will use product name)'; }
        @endphp
        <div class="mt-3 space-y-3">
            @include('admin.partials.seo-preview', [
                'title' => $previewTitle,
                'description' => $seoDesc,
                'url' => $previewUrl,
            ])
            @if($seoWarnings !== [])
            <ul class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded p-2 list-disc list-inside">
                @foreach($seoWarnings as $warning)
                <li>{{ $warning }}</li>
                @endforeach
            </ul>
            @endif
            <div>
                <label class="text-sm text-gray-600 block mb-1">SEO title <span class="text-gray-400">({{ strlen($seoTitle) }} chars — aim ~50–60)</span></label>
                <input name="meta_title" value="{{ $seoTitle }}" placeholder="SEO title (blank = product name)" class="w-full border px-3 py-2 rounded bg-white">
            </div>
            <div>
                <label class="text-sm text-gray-600 block mb-1">Meta description <span class="text-gray-400">({{ strlen($seoDesc) }} chars — aim ~140–160)</span></label>
                <textarea name="meta_description" rows="2" placeholder="Meta description" class="w-full border px-3 py-2 rounded bg-white">{{ $seoDesc }}</textarea>
            </div>
            <input name="og_image" value="{{ old('og_image', $product->og_image ?? '') }}" placeholder="Open Graph image URL (blank = product image)" class="w-full border px-3 py-2 rounded bg-white">
            <input name="image_alt" value="{{ old('image_alt', $product->image_alt ?? '') }}" placeholder="Primary image alt text (blank = product name)" class="w-full border px-3 py-2 rounded bg-white">
            <input name="canonical_url" value="{{ old('canonical_url', $product->canonical_url ?? '') }}" placeholder="Canonical URL override (blank = /shop/slug)" class="w-full border px-3 py-2 rounded bg-white">
            <input name="seo_keyword" value="{{ old('seo_keyword', $product->seo_keyword ?? '') }}" placeholder="Internal target keyword (not shown on site)" class="w-full border px-3 py-2 rounded bg-white">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="robots_index" value="1" @checked(old('robots_index', $product->robots_index ?? true))> Allow search indexing</label>
            <div class="grid md:grid-cols-2 gap-2">
                <input name="material" value="{{ old('material', $product->material ?? '') }}" placeholder="Material (optional, for structured data)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="finish" value="{{ old('finish', $product->finish ?? '') }}" placeholder="Finish (optional)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="color" value="{{ old('color', $product->color ?? '') }}" placeholder="Colour (optional)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="weight_kg" type="number" step="0.001" min="0" value="{{ old('weight_kg', $product->weight_kg ?? '') }}" placeholder="Weight kg (optional)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="gtin" value="{{ old('gtin', $product->gtin ?? '') }}" placeholder="GTIN (only if genuine)" class="w-full border px-3 py-2 rounded bg-white">
                <input name="mpn" value="{{ old('mpn', $product->mpn ?? '') }}" placeholder="MPN (only if genuine)" class="w-full border px-3 py-2 rounded bg-white">
            </div>
        </div>
    </details>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-sm text-gray-600 block mb-1">Section</label>
            <select name="section" id="product-section" required class="w-full border px-3 py-2 rounded">
                <option value="">Select section</option>
                @foreach($sectionLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentSection === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('section')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm text-gray-600 block mb-1">Parent category / service</label>
            <select name="category_id" id="product-category" required class="w-full border px-3 py-2 rounded">
                <option value="">Select category</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        data-section="{{ $categorySections[$category->id] ?? 'other' }}"
                        data-slug="{{ $category->slug }}"
                        @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }} ({{ $category->slug }})</option>
                @endforeach
            </select>
            @error('category_id')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="text-sm text-gray-600 block mb-1">Purchase mode</label>
            <select name="purchase_mode" id="product-purchase-mode" required class="w-full border px-3 py-2 rounded">
                <option value="">Select purchase mode</option>
                @foreach($purchaseModeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentPurchaseMode === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Auto-set from Section (Shop→Checkout, Studio→Enquiry, Railings→Quote).</p>
            @error('purchase_mode')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-sm text-gray-600 block mb-1">Pricing type</label>
            <select name="pricing_type" required class="w-full border px-3 py-2 rounded">
                <option value="">Select pricing type</option>
                @foreach($pricingTypeLabels as $value => $label)
                    <option value="{{ $value }}" @selected($currentPricingType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('pricing_type')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>
    </div>

    @php
        $adminPrice = old('price', $product->price ?? null);
        $adminCompare = old('compare_price', $product->compare_price ?? null);
        $adminDiscountPct = '';
        if (is_numeric($adminPrice) && is_numeric($adminCompare) && (float) $adminCompare > (float) $adminPrice && (float) $adminCompare > 0) {
            $adminDiscountPct = (string) (int) round((1 - ((float) $adminPrice / (float) $adminCompare)) * 100);
        }
    @endphp
    <fieldset id="pricing-discount-section" class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3" aria-labelledby="pricing-discount-heading">
        <legend id="pricing-discount-heading" class="text-sm font-semibold text-gray-900 px-1">Price &amp; discount (as on website)</legend>
        <p id="pricing-discount-intro" class="text-xs text-gray-600 -mt-1">On the product page and cards: selling price, optional strikethrough original price, and a <strong>−%</strong> badge when compare price is higher than price. Use Discount % to keep price and compare in sync.</p>
        <p id="door-handle-price-note" class="text-xs text-amber-800 bg-amber-100 border border-amber-200 rounded px-2 py-1.5{{ ($showDoorHandleSizes || $showMirrorSizes) ? '' : ' hidden' }}">Door handles &amp; mirror frames: set <strong>price per size</strong> in the section below. This Price field syncs to the lowest size price on save.</p>
        <div id="pricing-discount-grid" class="grid gap-4 {{ ($showDoorHandleSizes || $showMirrorSizes) ? 'grid-cols-1' : 'grid-cols-3' }}">
            <div>
                <label for="product-price" class="text-sm font-medium text-gray-800 block mb-1">Price @if($currentPricingType === 'square_foot')<span class="font-normal text-gray-500">(₹ per sq ft)</span>@endif</label>
                <input id="product-price" type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" placeholder="Selling price" required class="w-full border px-3 py-2 rounded bg-white">
                @error('price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                <p id="product-price-help" class="text-xs text-gray-500 mt-1">{{ $showDoorHandleSizes ? 'Lowest size price syncs here on save.' : 'Selling price shown on the website.' }}</p>
            </div>
            <div id="product-level-discount-fields" class="contents{{ ($showDoorHandleSizes || $showMirrorSizes) ? ' hidden' : '' }}">
                <div>
                    <label for="product-compare-price" class="text-sm font-medium text-gray-800 block mb-1">Compare price <span class="font-normal text-gray-500">(original)</span></label>
                    <input id="product-compare-price" type="number" step="0.01" name="compare_price" value="{{ old('compare_price', $product->compare_price ?? '') }}" placeholder="e.g. 38999" class="w-full border px-3 py-2 rounded bg-white">
                    @error('compare_price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                    <p class="text-xs text-gray-500 mt-1">Strikethrough when higher than Price.</p>
                </div>
                <div>
                    <label for="product-discount-pct" class="text-sm font-medium text-gray-800 block mb-1">Discount %</label>
                    <input id="product-discount-pct" type="number" step="1" min="0" max="99" value="{{ $adminDiscountPct }}" placeholder="e.g. 20" class="w-full border px-3 py-2 rounded bg-white" inputmode="numeric">
                    <p class="text-xs text-gray-500 mt-1">Edits sync Price ↔ Compare. Not stored separately.</p>
                </div>
            </div>
        </div>
        <p id="pricing-discount-footer" class="text-xs text-gray-500{{ ($showDoorHandleSizes || $showMirrorSizes) ? ' hidden' : '' }}">Shop = fixed selling price. Studio = rate per sq ft.</p>
        <p id="discount-preview" class="text-sm text-gray-700 hidden" aria-live="polite">
            Website preview: <span class="inline-flex items-center gap-2 flex-wrap">
                <span id="discount-preview-price" class="font-medium"></span>
                <span id="discount-preview-old" class="line-through text-gray-500"></span>
                <span id="discount-preview-badge" class="inline-block bg-gray-900 text-white text-xs px-2 py-0.5 rounded"></span>
            </span>
        </p>
    </fieldset>

    @php
        $sizeOptionRows = old('size_options', ($showDoorHandleSizes && isset($product)) ? ($product->size_options ?? []) : []);
        if (! is_array($sizeOptionRows) || $sizeOptionRows === []) {
            $sizeOptionRows = [['label' => '', 'price' => '', 'compare_price' => '', 'size_inches' => '', 'sku_suffix' => '']];
        }
    @endphp
    <fieldset id="size-options-section" class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4 space-y-3{{ $showDoorHandleSizes ? '' : ' hidden' }}" aria-labelledby="size-options-heading" data-only-category="door-handles" @if(! $showDoorHandleSizes) inert @endif>
        <legend id="size-options-heading" class="text-sm font-semibold text-gray-900 px-1">Size &amp; price options (door handles — each size has its own price &amp; discount)</legend>
        <p class="text-xs text-gray-600 -mt-1">Door handles only. Each size needs a <strong>Price</strong> and either <strong>Compare (₹)</strong> or <strong>Discount %</strong> — they stay in sync. The website shows strikethrough + −% on each size line. Leave all rows blank to use the single Price above. Not used for mirrors.</p>
        <div id="size-options-rows" class="space-y-3">
            @foreach($sizeOptionRows as $index => $row)
            @php
                $rowPrice = $row['price'] ?? '';
                $rowCompare = $row['compare_price'] ?? '';
                $rowDiscountPct = '';
                if (is_numeric($rowPrice) && is_numeric($rowCompare) && (float) $rowCompare > (float) $rowPrice && (float) $rowCompare > 0) {
                    $rowDiscountPct = (string) (int) round((1 - ((float) $rowPrice / (float) $rowCompare)) * 100);
                }
            @endphp
            <div class="size-option-row bg-white border rounded p-3 space-y-2">
                <div class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Size label</label>
                        <input type="text" name="size_options[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder='e.g. 8"' class="w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Price (₹)</label>
                        <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][price]" value="{{ $rowPrice }}" placeholder="800" class="size-opt-price w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Compare (₹)</label>
                        <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][compare_price]" value="{{ $rowCompare }}" placeholder="3200" class="size-opt-compare w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">Discount %</label>
                        <input type="number" step="1" min="0" max="99" name="size_options[{{ $index }}][discount_percent]" value="{{ $rowDiscountPct }}" placeholder="75" class="size-opt-discount w-full border px-2 py-1 rounded text-sm" inputmode="numeric" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-1">
                        <label class="text-xs text-gray-600 block mb-1">Inches</label>
                        <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][size_inches]" value="{{ $row['size_inches'] ?? '' }}" placeholder="8" class="w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-2">
                        <label class="text-xs text-gray-600 block mb-1">SKU suffix</label>
                        <input type="text" name="size_options[{{ $index }}][sku_suffix]" value="{{ $row['sku_suffix'] ?? '' }}" placeholder="8IN" class="w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                    </div>
                    <div class="col-span-1">
                        <button type="button" class="size-option-remove text-xs text-red-600 hover:underline" @if($loop->first && count($sizeOptionRows) === 1) hidden @endif>Remove</button>
                    </div>
                </div>
                <p class="size-opt-preview text-xs text-gray-600 hidden" aria-live="polite">
                    Website: <span class="size-opt-preview__price font-medium"></span>
                    <span class="size-opt-preview__compare line-through text-gray-500"></span>
                    <span class="size-opt-preview__badge inline-block bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded"></span>
                </p>
            </div>
            @endforeach
        </div>
        <button type="button" id="size-option-add" class="text-sm text-gray-700 border border-gray-300 bg-white px-3 py-1 rounded hover:bg-gray-50" @disabled(! $showDoorHandleSizes)>+ Add size</button>
        @error('size_options')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @error('size_options.*.label')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @error('size_options.*.price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @error('size_options.*.compare_price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </fieldset>
    <div class="grid grid-cols-2 gap-4">
        <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="SKU" class="border px-3 py-2 rounded">
        <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" placeholder="Stock" required class="border px-3 py-2 rounded">
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="hide_when_out_of_stock" value="1" @checked(old('hide_when_out_of_stock', $product->hide_when_out_of_stock ?? false))>
        Hide from storefront when out of stock
    </label>
    <p class="text-xs text-gray-500 -mt-2">When stock is 0, this product is removed from shop listings and category galleries. The product page stays live with an out-of-stock message.</p>

    <div>
        <label class="text-sm text-gray-600 block mb-1">Upload Image</label>
        <input type="file" name="image_file" accept="{{ \App\Support\AdminImageUpload::acceptAttribute() }}" class="w-full border px-3 py-2 rounded">
        <p class="text-xs text-gray-500 mt-1">{{ \App\Support\ProductImageSizes::galleryAdminHint() }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ \App\Support\ProductImageSizes::pdpAdminHint() }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ \App\Support\AdminImageUpload::hintText() }}</p>
    </div>
    <div>
        <label class="text-sm text-gray-600 block mb-1">Or Image URL</label>
        <input type="text" name="image" value="{{ old('image', isset($product) && str_starts_with($product->image ?? '', 'http') ? $product->image : '') }}" placeholder="https://..." class="w-full border px-3 py-2 rounded">
    </div>
    @if(isset($product) && $product->imageUrl())
        <img src="{{ $product->imageUrl() }}" alt="" class="w-32 h-40 object-contain rounded border bg-gray-50">
    @endif

    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))> Featured</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))> Active</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_gallery_visible" value="1" @checked(old('is_gallery_visible', $product->is_gallery_visible ?? true))> Visible in gallery grids</label>
    <button type="submit" class="bg-gray-900 text-white px-6 py-2 rounded">Save</button>
</form>

<script>
(function () {
    var sectionSelect = document.getElementById('product-section');
    var categorySelect = document.getElementById('product-category');
    var purchaseModeSelect = document.getElementById('product-purchase-mode');
    var mirrorSizeOptionsSection = document.getElementById('mirror-size-options-section');
    var mirrorSizeOptionsRows = document.getElementById('mirror-size-options-rows');
    var mirrorSizeAdd = document.getElementById('mirror-size-add');
    var sizeOptionsSection = document.getElementById('size-options-section');
    var sizeOptionsRows = document.getElementById('size-options-rows');
    var sizeOptionAdd = document.getElementById('size-option-add');
    if (!sectionSelect || !categorySelect || !purchaseModeSelect) return;

    var purchaseModeForSection = { shop: 'checkout', studio: 'enquiry', railings: 'quote' };

    function formatInr(amount) {
        return '₹' + Math.round(amount).toLocaleString('en-IN');
    }

    function roundMoney(amount) {
        return Math.round(amount * 100) / 100;
    }

    function calcDiscountPct(price, compare) {
        if (isNaN(price) || isNaN(compare) || compare <= 0 || compare <= price || price < 0) {
            return null;
        }
        return Math.round((1 - price / compare) * 100);
    }

    function filterCategoryOptions() {
        var section = sectionSelect.value;
        var options = categorySelect.querySelectorAll('option[data-section]');
        options.forEach(function (opt) {
            var matches = !section || opt.dataset.section === section || opt.dataset.section === 'other';
            opt.hidden = !matches;
        });
    }

    function syncPurchaseMode() {
        var mode = purchaseModeForSection[sectionSelect.value];
        if (mode) purchaseModeSelect.value = mode;
    }

    function formatDimNumber(value) {
        if (Math.abs(value - Math.round(value)) < 0.05) return String(Math.round(value));
        return String(Math.round(value * 10) / 10);
    }

    function formatFeetInchesLabel(feet, inches) {
        if (!inches || inches <= 0) return feet + ' ft';
        return feet + ' ft ' + formatDimNumber(inches) + ' in';
    }

    function syncMirrorSizeOptionsSection() {
        if (!mirrorSizeOptionsSection) return;
        var selected = categorySelect.selectedOptions[0];
        var isMirrorFrames = selected && selected.dataset.slug === 'mirror-frames';
        mirrorSizeOptionsSection.classList.toggle('hidden', !isMirrorFrames);
        if (isMirrorFrames) {
            mirrorSizeOptionsSection.removeAttribute('inert');
        } else {
            mirrorSizeOptionsSection.setAttribute('inert', '');
        }
        if (mirrorSizeOptionsRows) {
            mirrorSizeOptionsRows.querySelectorAll('input, select, button').forEach(function (input) {
                input.disabled = !isMirrorFrames;
            });
        }
        if (mirrorSizeAdd) mirrorSizeAdd.disabled = !isMirrorFrames;
    }

    function syncProductLevelPricing() {
        var selected = categorySelect.selectedOptions[0];
        var isDoorHandles = selected && selected.dataset.slug === 'door-handles';
        var isMirrorFrames = selected && selected.dataset.slug === 'mirror-frames';
        var usesSizeRows = isDoorHandles || isMirrorFrames;
        var pricingGrid = document.getElementById('pricing-discount-grid');
        var productDiscountFields = document.getElementById('product-level-discount-fields');
        var doorHandleNote = document.getElementById('door-handle-price-note');
        var pricingIntro = document.getElementById('pricing-discount-intro');
        var pricingFooter = document.getElementById('pricing-discount-footer');
        var priceHelp = document.getElementById('product-price-help');
        var discountPreview = document.getElementById('discount-preview');

        if (pricingGrid) pricingGrid.classList.toggle('grid-cols-1', usesSizeRows);
        if (pricingGrid) pricingGrid.classList.toggle('grid-cols-3', !usesSizeRows);
        if (productDiscountFields) productDiscountFields.classList.toggle('hidden', usesSizeRows);
        if (doorHandleNote) doorHandleNote.classList.toggle('hidden', !usesSizeRows);
        if (pricingIntro) pricingIntro.classList.toggle('hidden', usesSizeRows);
        if (pricingFooter) pricingFooter.classList.toggle('hidden', usesSizeRows);
        if (priceHelp) priceHelp.textContent = usesSizeRows
            ? 'Lowest size price syncs here on save.'
            : 'Selling price shown on the website.';
        if (discountPreview && usesSizeRows) discountPreview.classList.add('hidden');
    }

    function syncMirrorDimensionsSection() {
        syncMirrorSizeOptionsSection();
    }

    function syncSizeOptionsSection() {
        if (!sizeOptionsSection) return;
        var selected = categorySelect.selectedOptions[0];
        var isDoorHandles = selected && selected.dataset.slug === 'door-handles';
        sizeOptionsSection.classList.toggle('hidden', !isDoorHandles);
        if (isDoorHandles) {
            sizeOptionsSection.removeAttribute('inert');
        } else {
            sizeOptionsSection.setAttribute('inert', '');
        }
        if (sizeOptionsRows) {
            sizeOptionsRows.querySelectorAll('input').forEach(function (input) {
                input.disabled = !isDoorHandles;
            });
        }
        if (sizeOptionAdd) sizeOptionAdd.disabled = !isDoorHandles;
        syncProductLevelPricing();
    }

    function updateSizeRowPreview(row) {
        var preview = row.querySelector('.size-opt-preview');
        if (!preview) return;
        var priceEl = row.querySelector('.size-opt-price');
        var compareEl = row.querySelector('.size-opt-compare');
        var price = parseFloat(priceEl && priceEl.value);
        var compare = parseFloat(compareEl && compareEl.value);
        var pct = calcDiscountPct(price, compare);
        var show = pct !== null;
        preview.classList.toggle('hidden', !show);
        if (!show) return;
        var priceSpan = preview.querySelector('.size-opt-preview__price');
        var compareSpan = preview.querySelector('.size-opt-preview__compare');
        var badgeSpan = preview.querySelector('.size-opt-preview__badge');
        if (priceSpan) priceSpan.textContent = formatInr(price);
        if (compareSpan) compareSpan.textContent = formatInr(compare);
        if (badgeSpan) badgeSpan.textContent = '-' + pct + '%';
    }

    function bindSizeOptionRow(row) {
        var removeBtn = row.querySelector('.size-option-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (!sizeOptionsRows) return;
                if (sizeOptionsRows.querySelectorAll('.size-option-row').length <= 1) {
                    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                    return;
                }
                row.remove();
                sizeOptionsRows.querySelectorAll('.size-option-remove').forEach(function (btn, index, all) {
                    btn.hidden = all.length <= 1;
                });
            });
        }

        var priceEl = row.querySelector('.size-opt-price');
        var compareEl = row.querySelector('.size-opt-compare');
        var discountEl = row.querySelector('.size-opt-discount');
        var rowSyncing = false;

        function syncRowPctFromPrices() {
            if (rowSyncing || !discountEl) return;
            rowSyncing = true;
            var pct = calcDiscountPct(parseFloat(priceEl && priceEl.value), parseFloat(compareEl && compareEl.value));
            discountEl.value = pct !== null ? String(pct) : '';
            updateSizeRowPreview(row);
            rowSyncing = false;
        }

        function syncRowPricesFromPct() {
            if (rowSyncing || !discountEl || !priceEl || !compareEl) return;
            rowSyncing = true;
            var pct = parseFloat(discountEl.value);
            var price = parseFloat(priceEl.value);
            var compare = parseFloat(compareEl.value);

            if (!isNaN(pct) && pct > 0 && pct < 100) {
                if (!isNaN(price) && price >= 0) {
                    compareEl.value = String(roundMoney(price / (1 - pct / 100)));
                } else if (!isNaN(compare) && compare > 0) {
                    priceEl.value = String(roundMoney(compare * (1 - pct / 100)));
                }
            }
            updateSizeRowPreview(row);
            rowSyncing = false;
        }

        row.syncRowPricesFromPct = syncRowPricesFromPct;

        if (priceEl) priceEl.addEventListener('input', syncRowPctFromPrices);
        if (compareEl) compareEl.addEventListener('input', syncRowPctFromPrices);
        if (discountEl) discountEl.addEventListener('input', syncRowPricesFromPct);
        updateSizeRowPreview(row);
    }

    if (sizeOptionsRows) {
        sizeOptionsRows.querySelectorAll('.size-option-row').forEach(bindSizeOptionRow);
    }

    if (sizeOptionAdd && sizeOptionsRows) {
        sizeOptionAdd.addEventListener('click', function () {
            var index = sizeOptionsRows.querySelectorAll('.size-option-row').length;
            var row = document.createElement('div');
            row.className = 'size-option-row bg-white border rounded p-3 space-y-2';
            row.innerHTML = ''
                + '<div class="grid grid-cols-12 gap-2 items-end">'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Size label</label>'
                + '<input type="text" name="size_options[' + index + '][label]" placeholder=\'e.g. 8"\' class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Price (₹)</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][price]" placeholder="800" class="size-opt-price w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Compare (₹)</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][compare_price]" placeholder="3200" class="size-opt-compare w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Discount %</label>'
                + '<input type="number" step="1" min="0" max="99" name="size_options[' + index + '][discount_percent]" placeholder="75" class="size-opt-discount w-full border px-2 py-1 rounded text-sm" inputmode="numeric"></div>'
                + '<div class="col-span-1"><label class="text-xs text-gray-600 block mb-1">Inches</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][size_inches]" placeholder="8" class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">SKU suffix</label>'
                + '<input type="text" name="size_options[' + index + '][sku_suffix]" placeholder="8IN" class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-1"><button type="button" class="size-option-remove text-xs text-red-600 hover:underline">Remove</button></div>'
                + '</div>'
                + '<p class="size-opt-preview text-xs text-gray-600 hidden" aria-live="polite">'
                + 'Website: <span class="size-opt-preview__price font-medium"></span> '
                + '<span class="size-opt-preview__compare line-through text-gray-500"></span> '
                + '<span class="size-opt-preview__badge inline-block bg-gray-900 text-white text-[10px] px-1.5 py-0.5 rounded"></span>'
                + '</p>';
            sizeOptionsRows.appendChild(row);
            bindSizeOptionRow(row);
            sizeOptionsRows.querySelectorAll('.size-option-remove').forEach(function (btn) {
                btn.hidden = false;
            });
        });
    }

    function bindMirrorSizeRow(row) {
        var shapeSelect = row.querySelector('.mirror-shape-select');
        var rectBlock = row.querySelector('.mirror-size-rect');
        var roundBlock = row.querySelector('.mirror-size-round');
        var removeBtn = row.querySelector('.mirror-size-remove');

        function syncShapeFields() {
            var isRound = shapeSelect && shapeSelect.value === 'round';
            if (rectBlock) rectBlock.classList.toggle('hidden', isRound);
            if (roundBlock) roundBlock.classList.toggle('hidden', !isRound);
            row.dataset.shape = isRound ? 'round' : 'rect';
        }

        if (shapeSelect) {
            shapeSelect.addEventListener('change', syncShapeFields);
            syncShapeFields();
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (!mirrorSizeOptionsRows) return;
                if (mirrorSizeOptionsRows.querySelectorAll('.mirror-size-row').length <= 1) {
                    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                    if (shapeSelect) shapeSelect.value = 'rect';
                    syncShapeFields();
                    return;
                }
                row.remove();
                mirrorSizeOptionsRows.querySelectorAll('.mirror-size-remove').forEach(function (btn, index, all) {
                    btn.hidden = all.length <= 1;
                });
            });
        }
    }

    if (mirrorSizeOptionsRows) {
        mirrorSizeOptionsRows.querySelectorAll('.mirror-size-row').forEach(bindMirrorSizeRow);
    }

    if (mirrorSizeAdd && mirrorSizeOptionsRows) {
        mirrorSizeAdd.addEventListener('click', function () {
            var index = mirrorSizeOptionsRows.querySelectorAll('.mirror-size-row').length;
            var row = document.createElement('div');
            row.className = 'mirror-size-row bg-gray-50 border rounded p-3 space-y-3';
            row.dataset.shape = 'rect';
            row.innerHTML = ''
                + '<div class="grid grid-cols-12 gap-2 items-end">'
                + '<div class="col-span-3"><label class="text-xs text-gray-600 block mb-1">Shape</label>'
                + '<select name="size_options[' + index + '][shape]" class="mirror-shape-select w-full border px-2 py-1 rounded text-sm bg-white">'
                + '<option value="rect">Rectangle (L × H)</option><option value="round">Round (diameter)</option></select></div>'
                + '<div class="col-span-3"><label class="text-xs text-gray-600 block mb-1">Label override</label>'
                + '<input type="text" name="size_options[' + index + '][label]" placeholder="Auto from dimensions" class="w-full border px-2 py-1 rounded text-sm bg-white"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Price (₹)</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][price]" class="size-opt-price w-full border px-2 py-1 rounded text-sm bg-white"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Compare (₹)</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][compare_price]" class="size-opt-compare w-full border px-2 py-1 rounded text-sm bg-white"></div>'
                + '<div class="col-span-1"><label class="text-xs text-gray-600 block mb-1">Disc %</label>'
                + '<input type="number" step="1" min="0" max="99" name="size_options[' + index + '][discount_percent]" class="size-opt-discount w-full border px-2 py-1 rounded text-sm bg-white"></div>'
                + '<div class="col-span-1"><button type="button" class="mirror-size-remove text-xs text-red-600 hover:underline">Remove</button></div>'
                + '</div>'
                + '<div class="mirror-size-rect grid grid-cols-2 gap-4">'
                + '<div class="space-y-2"><p class="text-xs font-medium text-gray-700">Length (width)</p>'
                + '<div class="grid grid-cols-2 gap-2">'
                + '<input type="number" step="1" min="0" max="50" name="size_options[' + index + '][dim_width_ft]" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '<input type="number" step="0.1" min="0" max="11.9" name="size_options[' + index + '][dim_width_in]" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '</div></div>'
                + '<div class="space-y-2"><p class="text-xs font-medium text-gray-700">Height</p>'
                + '<div class="grid grid-cols-2 gap-2">'
                + '<input type="number" step="1" min="0" max="50" name="size_options[' + index + '][dim_height_ft]" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '<input type="number" step="0.1" min="0" max="11.9" name="size_options[' + index + '][dim_height_in]" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '</div></div></div>'
                + '<div class="mirror-size-round grid grid-cols-2 gap-4 hidden">'
                + '<div class="space-y-2"><p class="text-xs font-medium text-gray-700">Diameter</p>'
                + '<div class="grid grid-cols-2 gap-2">'
                + '<input type="number" step="1" min="0" max="50" name="size_options[' + index + '][dim_diameter_ft]" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '<input type="number" step="0.1" min="0" max="11.9" name="size_options[' + index + '][dim_diameter_in]" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white">'
                + '</div></div></div>';
            mirrorSizeOptionsRows.appendChild(row);
            bindMirrorSizeRow(row);
            mirrorSizeOptionsRows.querySelectorAll('.mirror-size-remove').forEach(function (btn) {
                btn.hidden = false;
            });
        });
    }

    sectionSelect.addEventListener('change', function () {
        filterCategoryOptions();
        syncPurchaseMode();
        syncMirrorDimensionsSection();
        syncSizeOptionsSection();
    });

    categorySelect.addEventListener('change', function () {
        syncMirrorDimensionsSection();
        syncSizeOptionsSection();
        syncProductLevelPricing();
    });

    filterCategoryOptions();
    syncMirrorDimensionsSection();
    syncSizeOptionsSection();

    var priceInput = document.getElementById('product-price');
    var compareInput = document.getElementById('product-compare-price');
    var discountPctInput = document.getElementById('product-discount-pct');
    var discountPreview = document.getElementById('discount-preview');
    var discountPreviewPrice = document.getElementById('discount-preview-price');
    var discountPreviewOld = document.getElementById('discount-preview-old');
    var discountPreviewBadge = document.getElementById('discount-preview-badge');
    var discountSyncing = false;

    function syncDiscountPreview() {
        if (!priceInput || !compareInput || !discountPreview) return;
        var price = parseFloat(priceInput.value);
        var compare = parseFloat(compareInput.value);
        var pct = calcDiscountPct(price, compare);
        var show = pct !== null;
        discountPreview.classList.toggle('hidden', !show);
        if (!show) return;
        if (discountPreviewPrice) discountPreviewPrice.textContent = formatInr(price);
        if (discountPreviewOld) discountPreviewOld.textContent = formatInr(compare);
        if (discountPreviewBadge) discountPreviewBadge.textContent = '-' + pct + '%';
    }

    function syncPctFromPrices() {
        if (discountSyncing || !discountPctInput) return;
        discountSyncing = true;
        var pct = calcDiscountPct(parseFloat(priceInput && priceInput.value), parseFloat(compareInput && compareInput.value));
        discountPctInput.value = pct !== null ? String(pct) : '';
        syncDiscountPreview();
        discountSyncing = false;
    }

    function syncPricesFromPct() {
        if (discountSyncing || !discountPctInput || !priceInput || !compareInput) return;
        discountSyncing = true;
        var pct = parseFloat(discountPctInput.value);
        var price = parseFloat(priceInput.value);
        var compare = parseFloat(compareInput.value);

        if (!isNaN(pct) && pct > 0 && pct < 100) {
            if (!isNaN(price) && price >= 0) {
                compareInput.value = String(roundMoney(price / (1 - pct / 100)));
            } else if (!isNaN(compare) && compare > 0) {
                priceInput.value = String(roundMoney(compare * (1 - pct / 100)));
            }
        }

        syncDiscountPreview();
        discountSyncing = false;
    }

    if (priceInput) priceInput.addEventListener('input', syncPctFromPrices);
    if (compareInput) compareInput.addEventListener('input', syncPctFromPrices);
    if (discountPctInput) discountPctInput.addEventListener('input', syncPricesFromPct);
    syncPctFromPrices();

    var productForm = document.querySelector('form[action*="products"]');
    if (productForm) {
        productForm.addEventListener('submit', function () {
            if (sizeOptionsRows) {
                sizeOptionsRows.querySelectorAll('.size-option-row').forEach(function (row) {
                    if (typeof row.syncRowPricesFromPct === 'function') {
                        row.syncRowPricesFromPct();
                    }
                });
            }
        });
    }
})();
</script>
@endsection
