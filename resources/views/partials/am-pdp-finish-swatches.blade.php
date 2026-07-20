@props([
    'swatches' => null,
    'defaultSlug' => 'champagne-mirror',
    'baseRate' => null,
])

@php
    use App\Models\Product;
    use App\Support\FinishSwatches;
    $options = $swatches ?? Product::finishSwatches();
    $baseRate = $baseRate ?? Product::baseSqFtRate();
    $default = collect($options)->firstWhere('slug', $defaultSlug) ?? $options[0];
    $columns = array_chunk($options, 2);
@endphp

<div class="am-pdp-finish" data-pdp-finish data-base-rate="{{ $baseRate }}">
    <p class="am-pdp-finish__heading">PVD Finish</p>
    <p class="am-pdp-finish__selected">
        Selected: <span class="am-pdp-finish__value" data-finish-label>{{ $default['name'] }}</span>
    </p>
    <div class="am-pdp-finish__grid" role="listbox" aria-label="Select PVD finish">
        @foreach($columns as $column)
        <div class="am-pdp-finish__col">
            @foreach($column as $swatch)
            @php
                $imgJpg = FinishSwatches::imageUrl($swatch['image']);
                $imgSvg = FinishSwatches::fallbackSvg($swatch['image']);
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
                <span class="am-pdp-finish__swatch-media">
                    <img src="{{ $imgJpg }}" alt="" class="am-pdp-finish__swatch-img"
                         data-finish-fallback="{{ $imgSvg }}"
                         onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback}">
                    <span class="am-pdp-finish__swatch-fallback" aria-hidden="true"></span>
                </span>
                <span class="am-pdp-finish__swatch-name">{{ $swatch['name'] }}</span>
            </button>
            @endforeach
        </div>
        @endforeach
    </div>
    <p class="am-pdp-finish__note">Black Mirror &amp; Black Brush: +30% on sq ft rate</p>
</div>
