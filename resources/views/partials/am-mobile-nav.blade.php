@php
    use App\Support\StorefrontUrl;

    $resolveNavHref = function (array $item): string {
        if (isset($item['route'])) {
            return StorefrontUrl::to($item['route'], $item['params'] ?? [], $item['href'] ?? '/');
        }

        return url($item['href'] ?? '#');
    };

    $isNavActive = function (array $item) use (&$isNavActive): bool {
        if (! empty($item['children'])) {
            foreach ($item['children'] as $child) {
                if ($isNavActive($child)) {
                    return true;
                }
            }

            return false;
        }

        if (! isset($item['route'])) {
            $href = ltrim($item['href'] ?? '', '/');

            return $href !== '' && request()->is($href);
        }

        $route = $item['route'];
        $params = $item['params'] ?? [];

        if ($route === 'shop.show' && request()->routeIs('shop.show')) {
            return request()->route('slug') === ($params['slug'] ?? null);
        }

        if ($route === 'shop.mirror-frames.index') {
            return request()->routeIs('shop.mirror-frames.*');
        }

        if ($route === 'studio.show' && request()->routeIs('studio.show')) {
            return request()->route('slug') === ($params['slug'] ?? null);
        }

        if ($route === 'studio.index') {
            return request()->routeIs('studio.index');
        }

        if ($route === 'railings.index') {
            return request()->routeIs('railings.index');
        }

        if ($route === 'services.show' && request()->routeIs('services.show')) {
            return request()->route('slug') === ($params['slug'] ?? null);
        }

        if ($route === 'collections.gallery.index' && request()->routeIs(['shop.show', 'collections.gallery.index'])) {
            return request()->route('slug') === ($params['slug'] ?? null);
        }

        if ($route === 'collections.mirror-frames.index') {
            return request()->routeIs(['shop.mirror-frames.*', 'collections.mirror-frames.*']);
        }

        if ($route === 'studio.railings') {
            return request()->routeIs(['railings.index', 'studio.railings']);
        }

        if ($route === 'shop.index' && request()->routeIs('shop.index') && isset($params['category'])) {
            return request('category') === $params['category'];
        }

        if ($route === 'shop.index' && request()->routeIs('shop.index') && ! isset($params['category'])) {
            return ! request()->has('category');
        }

        if (str_ends_with($route, '.index')) {
            return request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route);
        }

        return request()->routeIs($route);
    };
@endphp

<nav class="am-mobile-nav" id="am-mobile-nav" aria-label="Mobile">
    <button type="button" class="am-mobile-nav__close" id="am-menu-close" aria-label="Close menu">✕</button>
    @foreach($nav as $item)
        @if(!empty($item['children']))
            <div class="am-mobile-nav__group">
                <button type="button" class="am-mobile-nav__toggle {{ $isNavActive($item) ? 'is-active' : '' }}" data-am-nav-toggle aria-expanded="false">
                    {{ $item['label'] }}
                    <svg class="am-nav__chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg>
                </button>
                <div class="am-mobile-nav__sub">
                    @foreach($item['children'] as $child)
                        <a href="{{ $resolveNavHref($child) }}" class="{{ $isNavActive($child) ? 'is-active' : '' }}">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @else
            <a href="{{ $resolveNavHref($item) }}" class="{{ $isNavActive($item) ? 'is-active' : '' }}">{{ $item['label'] }}</a>
        @endif
    @endforeach
    <a href="{{ StorefrontUrl::to('cart.index', [], '/cart') }}">
        Cart{{ $cartCount ? ' ('.$cartCount.')' : '' }}
    </a>
</nav>
