@php
    use App\Support\StorefrontUrl;

    $resolveNavHref = function (array $item): string {
        if (isset($item['route'])) {
            return StorefrontUrl::to($item['route'], $item['params'] ?? [], $item['href'] ?? '/');
        }

        return url($item['href'] ?? '#');
    };
@endphp

<nav class="am-mobile-nav" id="am-mobile-nav" aria-label="Mobile">
    <button type="button" class="am-mobile-nav__close" id="am-menu-close" aria-label="Close menu">✕</button>
    @foreach($nav as $item)
        @if(!empty($item['children']))
            <div class="am-mobile-nav__group">
                <button type="button" class="am-mobile-nav__toggle" data-am-nav-toggle aria-expanded="false">
                    {{ $item['label'] }}
                    <svg class="am-nav__chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg>
                </button>
                <div class="am-mobile-nav__sub">
                    @foreach($item['children'] as $child)
                        <a href="{{ $resolveNavHref($child) }}">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @else
            <a href="{{ $resolveNavHref($item) }}">{{ $item['label'] }}</a>
        @endif
    @endforeach
    <a href="{{ route('cart.index') }}">Cart@if($cartCount) ({{ $cartCount }})@endif</a>
</nav>
