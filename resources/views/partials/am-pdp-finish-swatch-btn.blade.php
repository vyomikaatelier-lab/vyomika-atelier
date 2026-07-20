@php
    use App\Support\FinishSwatches;
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
