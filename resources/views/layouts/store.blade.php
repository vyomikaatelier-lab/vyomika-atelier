<!DOCTYPE html>
<html lang="en" data-hero="fullscreen" data-theme="atelier">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Vyomika Atelier LLP — PVD partitions, metal furniture, and bespoke fabrication. Pan-India delivery from Mumbai.">
    <title>@yield('title', 'Vyomika Atelier LLP — PVD Partitions & Metal Furniture')</title>
    @stack('meta')
    @if(filter_var(env('APP_PREVIEW_BAR', false), FILTER_VALIDATE_BOOLEAN))
    <script>try{var t=localStorage.getItem('ssmetal-theme');document.documentElement.dataset.theme=t||'atelier';var h=localStorage.getItem('ssmetal-hero');document.documentElement.dataset.hero=h||'fullscreen'}catch(e){document.documentElement.dataset.theme='atelier';document.documentElement.dataset.hero='fullscreen'}</script>
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/amerce.css') }}">
    <link rel="stylesheet" href="{{ asset('css/amerce-themes.css') }}">
    @stack('styles')
</head>
<body @if(filter_var(env('APP_PREVIEW_BAR', false), FILTER_VALIDATE_BOOLEAN)) class="has-preview-bar" @endif>

@php
    $previewBar = filter_var(env('APP_PREVIEW_BAR', false), FILTER_VALIDATE_BOOLEAN);
@endphp
@if($previewBar)
    @include('partials.am-preview-bar')
@endif

@php
    use App\Support\StorefrontUrl;

    $brand = \App\Support\SiteContent::brand();
    $announcement = \App\Support\SiteContent::announcement();
    $footer = \App\Support\SiteContent::footer();
    $cartService = app(\App\Services\CartService::class);
    $cartCount = $cartService->count();
    $cartItems = $cartService->all();
    $cartSubtotal = $cartService->subtotal();
    $nav = config('site.nav', []);
    $legalLinks = \App\Support\LegalContent::footerLinks();
    $storefrontLink = fn (string $name, array $params = [], string $fallback = '#') => StorefrontUrl::to($name, $params, $fallback);
@endphp

@if(!empty($announcement['text']))
<div class="am-announce">
    <div class="am-announce__track">
        <span>{{ $announcement['text'] }}</span>
        @if(!empty($announcement['link_label']))
        <a href="{{ url($announcement['link_href'] ?? '/shop') }}">{{ $announcement['link_label'] }}</a>
        @endif
        <span aria-hidden="true">{{ $announcement['text'] }}</span>
        @if(!empty($announcement['link_label']))
        <a href="{{ url($announcement['link_href'] ?? '/shop') }}" aria-hidden="true">{{ $announcement['link_label'] }}</a>
        @endif
    </div>
</div>
@endif

<header class="am-header">
    <div class="am-container am-header__inner">
        <a href="{{ $storefrontLink('home', [], '/') }}" class="am-logo">
            <span class="am-logo__name">{{ $brand['name'] ?? 'Vyomika Atelier LLP' }}</span>
            <span class="am-logo__tag">{{ $brand['tagline'] ?? 'PVD Partitions & Metal Furniture' }}</span>
        </a>

        @include('partials.am-nav')

        <div class="am-header__actions">
            <button type="button" class="am-icon-btn" id="am-search-toggle" aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
            </button>
            <a href="{{ $storefrontLink('account', [], '/account/login') }}" class="am-icon-btn" aria-label="Account">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
            </a>
            <a href="#" class="am-icon-btn" id="am-cart-toggle" aria-label="Cart">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M6 6L5 3H2"/></svg>
                @if($cartCount)<span class="am-cart-count">{{ $cartCount }}</span>@endif
            </a>
            <button type="button" class="am-icon-btn am-menu-toggle" id="am-menu-toggle" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>
</header>

<div class="am-search-bar" id="am-search-bar">
    <form action="{{ $storefrontLink('shop.index', [], '/shop') }}" method="GET" class="am-container">
        <input type="search" name="search" placeholder="Search partitions, furniture, handles…" aria-label="Search products">
        <button type="submit" class="am-btn am-btn--primary">Search</button>
        <button type="button" class="am-btn am-btn--outline" id="am-search-close">Close</button>
    </form>
</div>

@include('partials.am-mobile-nav')

@if(session('success'))
<div class="am-alert am-alert--success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="am-alert am-alert--error">{{ session('error') }}</div>
@endif
@if(isset($errors) && $errors->any())
<div class="am-alert am-alert--error">
    @foreach($errors->all() as $error){{ $error }}@if(!$loop->last) · @endif @endforeach
</div>
@endif

<main>@yield('content')</main>

<footer class="am-footer">
    <div class="am-container">
        <div class="am-footer__top">
            <div class="am-footer__brand">
                <a href="{{ $storefrontLink('home', [], '/') }}" class="am-logo">
                    <span class="am-logo__name">{{ trim(($brand['name'] ?? 'Vyomika Atelier LLP') . ' ' . ($brand['suffix'] ?? '')) }}</span>
                </a>
                <p>{{ $footer['newsletter'] ?? '' }}</p>
                <form class="am-footer__newsletter" action="#" method="POST" onsubmit="return false">
                    <input type="email" placeholder="Your email address" aria-label="Email for newsletter">
                    <button type="submit" class="am-btn am-btn--primary am-btn--sm">Subscribe</button>
                </form>
                <p style="margin-top:1.25rem;font-size:0.8rem">
                    {{ $brand['address_shop'] ?? 'Pan-India fabrication & delivery' }}<br>
                    {{ $brand['address_office'] ?? 'Mumbai, India' }}
                </p>
            </div>
            <div>
                <h5>Shop</h5>
                <ul>
                    @foreach($footer['shop_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/shop') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Information</h5>
                <ul>
                    @foreach($footer['info_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], [], '/') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Studio</h5>
                <ul>
                    @foreach($footer['service_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/services') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Legal</h5>
                <ul>
                    @foreach(\App\Support\LegalContent::footerLinks() as $link)
                    <li><a href="{{ $storefrontLink($link['route'], [], '/privacy-policy') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="am-footer__bottom">
            <span>© {{ date('Y') }} {{ trim(($brand['name'] ?? 'Vyomika Atelier LLP') . ' ' . ($brand['suffix'] ?? '')) }}. All rights reserved.</span>
            <div class="am-footer__contact">
                <a href="mailto:{{ $brand['email'] ?? '' }}">{{ $brand['email'] ?? '' }}</a>
                <a href="tel:{{ preg_replace('/\s+/', '', $brand['phone'] ?? '') }}">{{ $brand['phone'] ?? '' }}</a>
            </div>
        </div>
    </div>
</footer>

<div class="am-overlay" id="am-overlay"></div>

<aside class="am-drawer" id="am-cart-drawer" aria-label="Shopping cart">
    <div class="am-drawer__head">
        <h3>Shopping Cart</h3>
        <button type="button" id="am-cart-close" aria-label="Close cart">✕</button>
    </div>
    <div class="am-drawer__body">
        @if($cartItems->isNotEmpty())
        <ul class="am-cart-lines">
            @foreach($cartItems as $item)
            <li class="am-cart-line">
                @if($item['product']->imageUrl())
                <img src="{{ $item['product']->imageUrl() }}" alt="" class="am-cart-line__thumb">
                @endif
                <div class="am-cart-line__body">
                    <a href="{{ $storefrontLink('shop.show', ['slug' => $item['product']->slug], '/shop/'.$item['product']->slug) }}" class="am-cart-line__name">{{ $item['product']->name }}</a>
                    <p class="am-cart-line__meta">Qty {{ $item['quantity'] }} · ₹{{ number_format($item['line_total'], 0) }}</p>
                </div>
            </li>
            @endforeach
        </ul>
        <p class="am-cart-subtotal">Subtotal <strong>₹{{ number_format($cartSubtotal, 0) }}</strong></p>
        @else
        <p class="am-cart-empty">Your cart is currently empty.</p>
        @endif
    </div>
    <div class="am-drawer__foot">
        <a href="{{ $storefrontLink('cart.index', [], '/cart') }}" class="am-btn am-btn--primary am-btn--full">View Cart</a>
        <a href="{{ $storefrontLink('shop.index', [], '/shop') }}" class="am-btn am-btn--outline am-btn--full" style="margin-top:0.5rem">Continue Shopping</a>
    </div>
</aside>

<div class="am-modal" id="am-quickview" role="dialog" aria-label="Quick view">
    <button type="button" class="am-modal__close" id="am-quickview-close" aria-label="Close">✕</button>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;padding:2rem">
        <div><img data-qv-img src="" alt="" style="width:100%;border-radius:var(--am-radius)"></div>
        <div>
            <p class="am-featured__cat">PVD Partitions</p>
            <h3 data-qv-name style="font-family:var(--am-display);font-size:1.5rem;margin-bottom:1rem"></h3>
            <p class="am-featured__price-current" data-qv-price style="font-size:1.25rem;font-weight:700;margin-bottom:1.5rem"></p>
            <a href="{{ $storefrontLink('shop.index', [], '/shop') }}" class="am-btn am-btn--primary am-btn--full" data-qv-link>View Full Details</a>
        </div>
    </div>
</div>

@include('partials.am-popup-form-modal')

<script src="{{ asset('js/amerce.js') }}"></script>
<script src="{{ asset('js/calculator.js') }}"></script>
@if($previewBar ?? false)
<script src="{{ asset('js/preview-options.js') }}"></script>
@endif
@stack('scripts')
</body>
</html>
