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
    $showMirrorDimensions = $selectedCategorySlug === 'mirror-frames';
@endphp

@if(request('saved') || session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm max-w-2xl">{{ session('success') ?: 'Product saved successfully.' }}</div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ isset($product) ? route('admin.products.update', $product) : route('admin.products.store') }}" class="bg-white p-6 rounded-lg shadow max-w-2xl space-y-4">
    @csrf
    @if(isset($product)) @method('PUT') @endif
    <input type="hidden" name="_page_save" value="1">

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
            <p class="text-xs text-gray-500 mb-2">Body text below price and PVD finish swatches. HTML is allowed.</p>
            <textarea id="description" name="description" rows="5" placeholder="Product description shown on the storefront" class="w-full border px-3 py-2 rounded bg-white">{{ old('description', $product->description ?? '') }}</textarea>
            @error('description')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="swatches_note" class="text-sm font-medium text-gray-800 block mb-1">Note below PVD finish swatches <span class="font-normal text-gray-500">(optional)</span></label>
            <p class="text-xs text-gray-500 mb-2">Shown under the finish colour swatches. Leave blank for the default black-finish +30% note.</p>
            <input id="swatches_note" type="text" name="swatches_note" value="{{ old('swatches_note', $product->swatches_note ?? '') }}" placeholder="Black Mirror &amp; Black Brush: +30% on sq ft rate" class="w-full border px-3 py-2 rounded bg-white">
            @error('swatches_note')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        </div>

        <fieldset id="mirror-dimensions-section" class="space-y-3 border-t border-gray-200 pt-4{{ $showMirrorDimensions ? '' : ' hidden' }}" aria-labelledby="mirror-dimensions-heading" @if(! $showMirrorDimensions) inert @endif>
            <legend id="mirror-dimensions-heading" class="text-sm font-medium text-gray-800 px-1">Mirror dimensions (single size, shown as ft / mm / cm — one price above)</legend>
            <p class="text-xs text-gray-500 -mt-1 mb-2">Enter width and height in centimetres once. The storefront shows the same size in feet, millimetres, and centimetres. Mirrors use the single Price field above — not multiple size/price variants. Leave both blank to hide dimensions.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="dim_width_cm" class="text-sm text-gray-700 block mb-1">Width (cm)</label>
                    <input id="dim_width_cm" type="number" step="0.01" min="0.1" name="dim_width_cm" value="{{ old('dim_width_cm', $product->dim_width_cm ?? '') }}" placeholder="e.g. 61" class="w-full border px-3 py-2 rounded bg-white" @disabled(! $showMirrorDimensions)>
                    @error('dim_width_cm')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="dim_height_cm" class="text-sm text-gray-700 block mb-1">Height (cm)</label>
                    <input id="dim_height_cm" type="number" step="0.01" min="0.1" name="dim_height_cm" value="{{ old('dim_height_cm', $product->dim_height_cm ?? '') }}" placeholder="e.g. 91" class="w-full border px-3 py-2 rounded bg-white" @disabled(! $showMirrorDimensions)>
                    @error('dim_height_cm')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>

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
                <label for="tab_packaging" class="text-sm text-gray-700 block mb-1">Packaging tab</label>
                <textarea id="tab_packaging" name="tab_packaging" rows="5" placeholder="HTML for the Packaging tab" class="w-full border px-3 py-2 rounded font-mono text-sm bg-white">{{ old('tab_packaging', $product->tab_packaging ?? '') }}</textarea>
                @error('tab_packaging')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tab_shipping" class="text-sm text-gray-700 block mb-1">Shipping tab</label>
                <textarea id="tab_shipping" name="tab_shipping" rows="5" placeholder="HTML for the Shipping tab" class="w-full border px-3 py-2 rounded font-mono text-sm bg-white">{{ old('tab_shipping', $product->tab_shipping ?? '') }}</textarea>
                @error('tab_shipping')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
            </div>
        </fieldset>
    </section>

    <details class="border rounded p-3 bg-gray-50">
        <summary class="font-medium cursor-pointer text-sm">SEO</summary>
        <div class="mt-3 space-y-2">
            <input name="meta_title" value="{{ old('meta_title', $product->meta_title ?? '') }}" placeholder="SEO title (blank = product name)" class="w-full border px-3 py-2 rounded">
            <textarea name="meta_description" rows="2" placeholder="Meta description" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
            <input name="og_image" value="{{ old('og_image', $product->og_image ?? '') }}" placeholder="Open Graph image URL (blank = product image)" class="w-full border px-3 py-2 rounded">
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
    <fieldset class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3" aria-labelledby="pricing-discount-heading">
        <legend id="pricing-discount-heading" class="text-sm font-semibold text-gray-900 px-1">Price &amp; discount (as on website)</legend>
        <p class="text-xs text-gray-600 -mt-1">On the product page and cards: selling price, optional strikethrough original price, and a <strong>−%</strong> badge when compare price is higher than price. Use Discount % to keep price and compare in sync.</p>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="product-price" class="text-sm font-medium text-gray-800 block mb-1">Price @if($currentPricingType === 'square_foot')<span class="font-normal text-gray-500">(₹ per sq ft)</span>@endif</label>
                <input id="product-price" type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" placeholder="Selling price" required class="w-full border px-3 py-2 rounded bg-white">
                @error('price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-500 mt-1">Selling price shown on the website.</p>
            </div>
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
        <p class="text-xs text-gray-500">Shop = fixed selling price. Studio = rate per sq ft. Door handles with size options sync Price to the lowest size price.</p>
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
            $sizeOptionRows = [['label' => '', 'price' => '', 'size_inches' => '', 'sku_suffix' => '']];
        }
    @endphp
    <fieldset id="size-options-section" class="rounded-lg border-2 border-amber-200 bg-amber-50 p-4 space-y-3{{ $showDoorHandleSizes ? '' : ' hidden' }}" aria-labelledby="size-options-heading" data-only-category="door-handles" @if(! $showDoorHandleSizes) inert @endif>
        <legend id="size-options-heading" class="text-sm font-semibold text-gray-900 px-1">Size &amp; price options (door handles — each size has its own price)</legend>
        <p class="text-xs text-gray-600 -mt-1">Door handles only. Add each size with its own price (e.g. 8&quot; → ₹800, 12&quot; → ₹1500). Visitors pick a size on the product page; price updates automatically. Leave all rows blank to use the single price above. Not used for mirrors.</p>
        <div id="size-options-rows" class="space-y-3">
            @foreach($sizeOptionRows as $index => $row)
            <div class="size-option-row grid grid-cols-12 gap-2 items-end bg-white border rounded p-3">
                <div class="col-span-4">
                    <label class="text-xs text-gray-600 block mb-1">Size label</label>
                    <input type="text" name="size_options[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder='e.g. 8"' class="w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                </div>
                <div class="col-span-3">
                    <label class="text-xs text-gray-600 block mb-1">Price (₹)</label>
                    <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" placeholder="800" class="w-full border px-2 py-1 rounded text-sm" @disabled(! $showDoorHandleSizes)>
                </div>
                <div class="col-span-2">
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
            @endforeach
        </div>
        <button type="button" id="size-option-add" class="text-sm text-gray-700 border border-gray-300 bg-white px-3 py-1 rounded hover:bg-gray-50" @disabled(! $showDoorHandleSizes)>+ Add size</button>
        @error('size_options')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @error('size_options.*.label')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @error('size_options.*.price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </fieldset>
    <div class="grid grid-cols-2 gap-4">
        <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="SKU" class="border px-3 py-2 rounded">
        <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" placeholder="Stock" required class="border px-3 py-2 rounded">
    </div>

    <div>
        <label class="text-sm text-gray-600 block mb-1">Upload Image</label>
        <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp" class="w-full border px-3 py-2 rounded">
        <p class="text-xs text-gray-500 mt-1">JPEG, PNG or WebP. Max 4 MB.</p>
    </div>
    <div>
        <label class="text-sm text-gray-600 block mb-1">Or Image URL</label>
        <input type="text" name="image" value="{{ old('image', isset($product) && str_starts_with($product->image ?? '', 'http') ? $product->image : '') }}" placeholder="https://..." class="w-full border px-3 py-2 rounded">
    </div>
    @if(isset($product) && $product->imageUrl())
        <img src="{{ $product->imageUrl() }}" alt="" class="w-32 h-40 object-cover rounded border">
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
    var mirrorDimensionsSection = document.getElementById('mirror-dimensions-section');
    var sizeOptionsSection = document.getElementById('size-options-section');
    var sizeOptionsRows = document.getElementById('size-options-rows');
    var sizeOptionAdd = document.getElementById('size-option-add');
    if (!sectionSelect || !categorySelect || !purchaseModeSelect) return;

    var purchaseModeForSection = { shop: 'checkout', studio: 'enquiry', railings: 'quote' };

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

    function syncMirrorDimensionsSection() {
        if (!mirrorDimensionsSection) return;
        var selected = categorySelect.selectedOptions[0];
        var isMirrorFrames = selected && selected.dataset.slug === 'mirror-frames';
        mirrorDimensionsSection.classList.toggle('hidden', !isMirrorFrames);
        if (isMirrorFrames) {
            mirrorDimensionsSection.removeAttribute('inert');
        } else {
            mirrorDimensionsSection.setAttribute('inert', '');
        }
        mirrorDimensionsSection.querySelectorAll('input').forEach(function (input) {
            input.disabled = !isMirrorFrames;
        });
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
    }

    function bindSizeOptionRow(row) {
        var removeBtn = row.querySelector('.size-option-remove');
        if (!removeBtn) return;
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

    if (sizeOptionsRows) {
        sizeOptionsRows.querySelectorAll('.size-option-row').forEach(bindSizeOptionRow);
    }

    if (sizeOptionAdd && sizeOptionsRows) {
        sizeOptionAdd.addEventListener('click', function () {
            var index = sizeOptionsRows.querySelectorAll('.size-option-row').length;
            var row = document.createElement('div');
            row.className = 'size-option-row grid grid-cols-12 gap-2 items-end bg-white border rounded p-3';
            row.innerHTML = ''
                + '<div class="col-span-4"><label class="text-xs text-gray-600 block mb-1">Size label</label>'
                + '<input type="text" name="size_options[' + index + '][label]" placeholder=\'e.g. 8"\' class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-3"><label class="text-xs text-gray-600 block mb-1">Price (₹)</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][price]" placeholder="800" class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">Inches</label>'
                + '<input type="number" step="0.01" min="0" name="size_options[' + index + '][size_inches]" placeholder="8" class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-2"><label class="text-xs text-gray-600 block mb-1">SKU suffix</label>'
                + '<input type="text" name="size_options[' + index + '][sku_suffix]" placeholder="8IN" class="w-full border px-2 py-1 rounded text-sm"></div>'
                + '<div class="col-span-1"><button type="button" class="size-option-remove text-xs text-red-600 hover:underline">Remove</button></div>';
            sizeOptionsRows.appendChild(row);
            bindSizeOptionRow(row);
            sizeOptionsRows.querySelectorAll('.size-option-remove').forEach(function (btn) {
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
})();
</script>
@endsection
