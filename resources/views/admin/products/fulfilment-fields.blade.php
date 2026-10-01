@php
    $record = $product ?? null;
    $value = function (string $field, mixed $default = null) use ($record) {
        $stored = $record?->{$field} ?? $default;

        return old($field, $stored);
    };
    $modes = [
        'included' => 'Included in price',
        'fixed' => 'Fixed charge',
        'quoted' => 'Quoted separately',
    ];
    $bases = [
        'per_unit' => 'Per unit (amount × quantity)',
        'per_line' => 'Per product line (charged once)',
    ];
    $charges = [
        'shipping_india' => 'Shipping within India',
        'shipping_international' => 'International shipping',
        'packing_india' => 'Packing within India',
        'packing_international' => 'International packing',
    ];
@endphp

<fieldset class="mt-4 border border-gray-200 rounded p-4 space-y-4">
    <legend class="px-2 text-sm font-medium text-gray-800">Availability, production, shipping, and packing</legend>
    <p class="text-sm text-gray-600">These settings do not change whether the product is purchased, enquired, or quoted. International orders stay enquiry-only.</p>

    <label class="text-sm text-gray-600 block">Availability
        <select name="availability_mode" class="mt-1 w-full border px-3 py-2 rounded text-sm bg-white">
            @foreach(['ready_stock' => 'Ready stock', 'made_to_order' => 'Made to order', 'confirm_with_team' => 'Confirm with team'] as $mode => $label)
                <option value="{{ $mode }}" @selected($value('availability_mode', 'confirm_with_team') === $mode)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @error('availability_mode')<p class="text-red-600 text-sm">{{ $message }}</p>@enderror

    <div class="grid md:grid-cols-4 gap-3">
        <label class="text-sm text-gray-600 block">Production minimum
            <input type="number" name="production_min" min="1" max="520" value="{{ $value('production_min') }}" class="mt-1 w-full border px-3 py-2 rounded text-sm">
        </label>
        <label class="text-sm text-gray-600 block">Production maximum
            <input type="number" name="production_max" min="1" max="520" value="{{ $value('production_max') }}" class="mt-1 w-full border px-3 py-2 rounded text-sm">
        </label>
        <label class="text-sm text-gray-600 block">Unit
            <select name="production_unit" class="mt-1 w-full border px-3 py-2 rounded text-sm bg-white">
                <option value="">Not set</option>
                @foreach(['days' => 'Days', 'weeks' => 'Weeks'] as $unit => $label)
                    <option value="{{ $unit }}" @selected($value('production_unit') === $unit)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm text-gray-600 block">Starts at
            <select name="production_starts" class="mt-1 w-full border px-3 py-2 rounded text-sm bg-white">
                <option value="">Not set</option>
                <option value="order_confirmation" @selected($value('production_starts') === 'order_confirmation')>Order confirmation</option>
                <option value="final_specification_approval" @selected($value('production_starts') === 'final_specification_approval')>Final-specification approval</option>
            </select>
        </label>
    </div>
    <p class="text-sm text-gray-600">Required for made to order. This is production time, not dispatch or transit.</p>
    @foreach(['production_min', 'production_max', 'production_unit', 'production_starts'] as $field)
        @error($field)<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
    @endforeach

    @foreach($charges as $field => $label)
        <div class="grid md:grid-cols-3 gap-3 border-t border-gray-100 pt-3">
            <label class="text-sm text-gray-600 block">{{ $label }}
                <select name="{{ $field }}_mode" class="mt-1 w-full border px-3 py-2 rounded text-sm bg-white">
                    @foreach($modes as $mode => $modeLabel)
                        <option value="{{ $mode }}" @selected($value($field.'_mode', 'quoted') === $mode)>{{ $modeLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-gray-600 block">Fixed amount (INR)
                <input type="text" name="{{ $field }}_amount" value="{{ $value($field.'_amount') }}" inputmode="decimal" class="mt-1 w-full border px-3 py-2 rounded text-sm" placeholder="0.00">
            </label>
            <label class="text-sm text-gray-600 block">Charge basis
                <select name="{{ $field }}_basis" class="mt-1 w-full border px-3 py-2 rounded text-sm bg-white">
                    <option value="">Not set</option>
                    @foreach($bases as $basis => $basisLabel)
                        <option value="{{ $basis }}" @selected($value($field.'_basis') === $basis)>{{ $basisLabel }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @foreach([$field.'_mode', $field.'_amount', $field.'_basis'] as $errorField)
            @error($errorField)<p class="text-red-600 text-sm">{{ $message }}</p>@enderror
        @endforeach
    @endforeach

    @if($record?->needs_fulfilment_review || $record?->fulfilment_review_note)
        <div class="bg-amber-50 border border-amber-200 rounded p-3 text-sm text-amber-950">
            <p class="font-medium">Needs completion</p>
            <p class="mt-1 whitespace-pre-line">{{ $record->fulfilment_review_note }}</p>
        </div>
    @endif
    <label class="text-sm text-gray-700 flex items-center gap-2">
        <input type="hidden" name="needs_fulfilment_review" value="0">
        <input type="checkbox" name="needs_fulfilment_review" value="1" @checked(old('needs_fulfilment_review', $record?->needs_fulfilment_review))>
        Flag this product for fulfilment completion
    </label>
    <label class="text-sm text-gray-600 block">Review note
        <textarea name="fulfilment_review_note" rows="3" class="mt-1 w-full border px-3 py-2 rounded text-sm">{{ $value('fulfilment_review_note') }}</textarea>
    </label>
</fieldset>
