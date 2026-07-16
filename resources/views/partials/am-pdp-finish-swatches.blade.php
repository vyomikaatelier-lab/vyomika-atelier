@props([
    'swatches' => null,
    'defaultSlug' => 'champagne-mirror',
    'baseRate' => null,
])

@php
    use App\Models\Product;
    $options = $swatches ?? Product::finishSwatches();
    $baseRate = $baseRate ?? Product::baseSqFtRate();
    $default = collect($options)->firstWhere('slug', $defaultSlug) ?? $options[0];
@endphp

<div class="am-pdp-finish" data-pdp-finish data-base-rate="{{ $baseRate }}">
    <label class="am-pdp-finish__label">
        PVD Finish: <span class="am-pdp-finish__value" data-finish-label>{{ $default['name'] }}</span>
    </label>
    <div class="am-pdp-finish__swatches" role="listbox" aria-label="Select PVD finish">
        @foreach($options as $swatch)
        @php
            $imgJpg = asset($swatch['image']);
            $imgSvg = asset(str_replace('.jpg', '.svg', $swatch['image']));
        @endphp
        <button type="button"
            class="am-pdp-finish__swatch {{ $swatch['slug'] === $default['slug'] ? 'is-active' : '' }}"
            role="option"
            aria-selected="{{ $swatch['slug'] === $default['slug'] ? 'true' : 'false' }}"
            aria-label="{{ $swatch['name'] }}"
            data-finish-slug="{{ $swatch['slug'] }}"
            data-finish-name="{{ $swatch['name'] }}"
            data-finish-rate="{{ $swatch['rate'] }}"
            data-finish-black="{{ $swatch['is_black'] ? '1' : '0' }}"
            style="--swatch-color: {{ $swatch['hex'] }}"
            title="{{ $swatch['name'] }}{{ $swatch['is_black'] ? ' (+30%)' : '' }}">
            <img src="{{ $imgJpg }}" alt="" class="am-pdp-finish__swatch-img"
                 data-finish-fallback="{{ $imgSvg }}"
                 onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback}">
            <span class="am-pdp-finish__swatch-fallback" aria-hidden="true"></span>
        </button>
        @endforeach
    </div>
    <p class="am-pdp-finish__note">Black Mirror &amp; Black Brush: +30% on sq ft rate</p>
</div>
