@php
    use App\Models\Product;

    $rows = old('size_options', isset($product) && is_array($product->size_options) ? $product->size_options : []);

    if ($rows === [] && isset($product) && $product->hasMirrorDimensions()) {
        $widthFtIn = Product::feetInchesFromCm((float) $product->dim_width_cm);
        $heightFtIn = Product::feetInchesFromCm((float) $product->dim_height_cm);
        $rows = [[
            'shape' => 'rect',
            'dim_width_ft' => $widthFtIn['feet'],
            'dim_width_in' => $widthFtIn['inches'],
            'dim_height_ft' => $heightFtIn['feet'],
            'dim_height_in' => $heightFtIn['inches'],
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'label' => '',
        ]];
    }

    if (! is_array($rows) || $rows === []) {
        $rows = [['shape' => 'rect', 'label' => '', 'price' => '', 'compare_price' => '']];
    }

    $hasFilledMirrorSizes = collect($rows)->contains(function ($row) {
        $price = $row['price'] ?? null;

        return filled($price) && is_numeric($price);
    });

    $mirrorListingPrice = old('price', $product->price ?? '');
@endphp

<fieldset id="mirror-size-options-section" class="space-y-3 border-t border-gray-200 pt-4{{ $showMirrorSizes ? '' : ' hidden' }}" aria-labelledby="mirror-size-options-heading" @if(! $showMirrorSizes) inert @endif data-only-category="mirror-frames">
    <legend id="mirror-size-options-heading" class="text-sm font-medium text-gray-800 px-1">Mirror sizes &amp; prices</legend>
    <p class="text-xs text-gray-500 -mt-1 mb-2">Add one row per size. Rectangular mirrors use length × height; round designs use diameter. Each size has its own price. Leave all rows blank to hide the size selector on the website.</p>
    <div id="mirror-listing-price-wrap" class="rounded border border-gray-200 bg-white p-3 space-y-1{{ $hasFilledMirrorSizes ? ' hidden' : '' }}">
        <label for="mirror-listing-price" class="text-sm font-medium text-gray-800 block">Listing price (₹)</label>
        <p class="text-xs text-gray-500">Use when the mirror is sold at one price with no size selector. Add size rows below to set price per size instead.</p>
        <input type="number" step="0.01" min="0" id="mirror-listing-price" @if($showMirrorSizes && ! $hasFilledMirrorSizes) name="price" required @endif value="{{ $mirrorListingPrice }}" class="w-full border px-3 py-2 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
        @error('price')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    </div>
    <input type="hidden" id="mirror-sync-price" @if($showMirrorSizes && $hasFilledMirrorSizes) name="price" @endif value="{{ $mirrorListingPrice }}">
    <div id="mirror-size-options-rows" class="space-y-3">
        @foreach($rows as $index => $row)
        @php
            $shape = old("size_options.{$index}.shape", $row['shape'] ?? 'rect') === 'round' ? 'round' : 'rect';
            $rowPrice = old("size_options.{$index}.price", $row['price'] ?? '');
            $rowCompare = old("size_options.{$index}.compare_price", $row['compare_price'] ?? '');
            $rowDiscountPct = '';
            if (is_numeric($rowPrice) && is_numeric($rowCompare) && (float) $rowCompare > (float) $rowPrice && (float) $rowCompare > 0) {
                $rowDiscountPct = (string) (int) round((1 - ((float) $rowPrice / (float) $rowCompare)) * 100);
            }

            $widthCm = $row['dim_width_cm'] ?? null;
            $heightCm = $row['dim_height_cm'] ?? null;
            $diameterCm = $row['dim_diameter_cm'] ?? null;

            $widthFtIn = (old("size_options.{$index}.dim_width_ft") !== null || old("size_options.{$index}.dim_width_in") !== null)
                ? ['feet' => old("size_options.{$index}.dim_width_ft", ''), 'inches' => old("size_options.{$index}.dim_width_in", '')]
                : (is_numeric($widthCm) ? Product::feetInchesFromCm((float) $widthCm) : ['feet' => '', 'inches' => '']);
            $heightFtIn = (old("size_options.{$index}.dim_height_ft") !== null || old("size_options.{$index}.dim_height_in") !== null)
                ? ['feet' => old("size_options.{$index}.dim_height_ft", ''), 'inches' => old("size_options.{$index}.dim_height_in", '')]
                : (is_numeric($heightCm) ? Product::feetInchesFromCm((float) $heightCm) : ['feet' => '', 'inches' => '']);
            $diameterFtIn = (old("size_options.{$index}.dim_diameter_ft") !== null || old("size_options.{$index}.dim_diameter_in") !== null)
                ? ['feet' => old("size_options.{$index}.dim_diameter_ft", ''), 'inches' => old("size_options.{$index}.dim_diameter_in", '')]
                : (is_numeric($diameterCm) ? Product::feetInchesFromCm((float) $diameterCm) : ['feet' => '', 'inches' => '']);
        @endphp
        <div class="mirror-size-row bg-gray-50 border rounded p-3 space-y-3" data-shape="{{ $shape }}">
            <div class="grid grid-cols-12 gap-2 items-end">
                <div class="col-span-3">
                    <label class="text-xs text-gray-600 block mb-1">Shape</label>
                    <select name="size_options[{{ $index }}][shape]" class="mirror-shape-select w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                        <option value="rect" @selected($shape === 'rect')>Rectangle (L × H)</option>
                        <option value="round" @selected($shape === 'round')>Round (diameter)</option>
                    </select>
                </div>
                <div class="col-span-3">
                    <label class="text-xs text-gray-600 block mb-1">Label override</label>
                    <input type="text" name="size_options[{{ $index }}][label]" value="{{ old("size_options.{$index}.label", $row['label'] ?? '') }}" placeholder="Auto from dimensions" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-600 block mb-1">Price (₹)</label>
                    <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][price]" value="{{ $rowPrice }}" class="size-opt-price w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-600 block mb-1">Compare (₹)</label>
                    <input type="number" step="0.01" min="0" name="size_options[{{ $index }}][compare_price]" value="{{ $rowCompare }}" class="size-opt-compare w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                </div>
                <div class="col-span-1">
                    <label class="text-xs text-gray-600 block mb-1">Disc %</label>
                    <input type="number" step="1" min="0" max="99" name="size_options[{{ $index }}][discount_percent]" value="{{ $rowDiscountPct }}" class="size-opt-discount w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                </div>
                <div class="col-span-1">
                    <button type="button" class="mirror-size-remove text-xs text-red-600 hover:underline" @if($loop->first && count($rows) === 1) hidden @endif>Remove</button>
                </div>
            </div>
            <div class="mirror-size-rect grid grid-cols-2 gap-4{{ $shape === 'round' ? ' hidden' : '' }}">
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">Length (width)</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="1" min="0" max="50" name="size_options[{{ $index }}][dim_width_ft]" value="{{ $widthFtIn['feet'] === '' ? '' : $widthFtIn['feet'] }}" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                        <input type="number" step="0.1" min="0" max="11.9" name="size_options[{{ $index }}][dim_width_in]" value="{{ $widthFtIn['inches'] === '' ? '' : $widthFtIn['inches'] }}" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">Height</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="1" min="0" max="50" name="size_options[{{ $index }}][dim_height_ft]" value="{{ $heightFtIn['feet'] === '' ? '' : $heightFtIn['feet'] }}" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                        <input type="number" step="0.1" min="0" max="11.9" name="size_options[{{ $index }}][dim_height_in]" value="{{ $heightFtIn['inches'] === '' ? '' : $heightFtIn['inches'] }}" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                    </div>
                </div>
            </div>
            <div class="mirror-size-round grid grid-cols-2 gap-4{{ $shape === 'round' ? '' : ' hidden' }}">
                <div class="space-y-2">
                    <p class="text-xs font-medium text-gray-700">Diameter</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="1" min="0" max="50" name="size_options[{{ $index }}][dim_diameter_ft]" value="{{ $diameterFtIn['feet'] === '' ? '' : $diameterFtIn['feet'] }}" placeholder="ft" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                        <input type="number" step="0.1" min="0" max="11.9" name="size_options[{{ $index }}][dim_diameter_in]" value="{{ $diameterFtIn['inches'] === '' ? '' : $diameterFtIn['inches'] }}" placeholder="in" class="w-full border px-2 py-1 rounded text-sm bg-white" @disabled(! $showMirrorSizes)>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" id="mirror-size-add" class="text-sm text-gray-700 border border-gray-300 bg-white px-3 py-1 rounded hover:bg-gray-50" @disabled(! $showMirrorSizes)>+ Add size</button>
</fieldset>
