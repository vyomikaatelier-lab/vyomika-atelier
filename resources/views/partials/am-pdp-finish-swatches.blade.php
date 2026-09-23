@props([
    'swatches' => null,
    'defaultSlug' => \App\Support\FinishSwatches::defaultSlug(),
    'baseRate' => null,
    'note' => null,
])

@php
    use App\Models\Product;
    use App\Support\FinishSwatches;
    $options = $swatches ?? Product::finishSwatches();
    $baseRate = $baseRate ?? Product::baseSqFtRate();
    $selectedSlug = old('finish_slug', $defaultSlug);
    $default = collect($options)->firstWhere('slug', $selectedSlug)
        ?? collect($options)->firstWhere('slug', FinishSwatches::defaultSlug())
        ?? $options[0];
    $pairs = array_chunk($options, 2);
    $mirrorRow = array_map(fn (array $pair) => $pair[0], $pairs);
    $brushRow = array_values(array_filter(array_map(fn (array $pair) => $pair[1] ?? null, $pairs)));
@endphp

<div class="am-pdp-finish" data-pdp-finish data-base-rate="{{ $baseRate }}">
    <p class="am-pdp-finish__heading">PVD Finish</p>
    <p class="am-pdp-finish__selected">
        Selected: <span class="am-pdp-finish__value" data-finish-label>{{ $default['name'] }}</span>
    </p>
    <div class="am-pdp-finish__grid" role="listbox" aria-label="Select PVD finish">
        <div class="am-pdp-finish__row am-pdp-finish__row--mirror">
            @foreach($mirrorRow as $swatch)
            @include('partials.am-pdp-finish-swatch-btn', ['swatch' => $swatch, 'default' => $default])
            @endforeach
        </div>
        <div class="am-pdp-finish__row am-pdp-finish__row--brush">
            @foreach($brushRow as $swatch)
            @include('partials.am-pdp-finish-swatch-btn', ['swatch' => $swatch, 'default' => $default])
            @endforeach
        </div>
    </div>
    <p class="am-pdp-finish__note">{{ $note ?? 'Black Mirror &amp; Black Brush: +30% on sq ft rate' }}</p>
</div>
