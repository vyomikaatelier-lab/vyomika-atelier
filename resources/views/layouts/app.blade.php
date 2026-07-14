<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VYOMIKA ATELIER — Precision architectural metalwork, partitions, façades, and bespoke interiors.">
    <title>@yield('title', 'VYOMIKA ATELIER')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Outfit:wght@200;300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atelier.css') }}">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { serif: ['Cormorant Garamond', 'serif'], sans: ['Outfit', 'sans-serif'] },
                    colors: { brand: { 50:'#fafaf8', 100:'#f4f2ee', 200:'#e8e4dc', 400:'#9c968c', 500:'#b8956b', 700:'#5c4a38', 900:'#0c0c0c' } }
                }
            }
        }
    </script>
    @stack('styles')
</head>
<body class="font-sans text-brand-900 bg-brand-50 antialiased">

    @php $cartCount = app(\App\Services\CartService::class)->count(); @endphp

    <header class="va-header {{ request()->routeIs('home') ? 'va-header--hero' : 'va-header--solid' }}" id="va-header">
        <div class="max-w-[90rem] mx-auto px-5 lg:px-10 flex items-center justify-between gap-4">
            <button id="va-menu-btn" class="lg:hidden va-nav-link p-2 -ml-2 shrink-0" aria-label="Menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>

            <nav class="hidden lg:flex items-center gap-8 flex-1 min-w-0">
                <div class="va-nav-dropdown group relative">
                    <button type="button" class="va-nav-link flex items-center gap-1 py-2">Services</button>
                    <div class="va-dropdown-panel">
                        <a href="{{ route('services.index') }}" class="va-dropdown-title">All Services</a>
                        @foreach($navServices ?? [] as $svc)
                            <a href="{{ route('services.show', $svc->slug) }}">{{ $svc->name }}</a>
                        @endforeach
                    </div>
                </div>
                <a href="{{ route('shop.index') }}" class="va-nav-link">Shop</a>
                <a href="{{ route('blog.index') }}" class="va-nav-link">Blog</a>
                <a href="{{ request()->routeIs('home') ? '#how-it-works' : route('home') . '#how-it-works' }}" class="va-nav-link">How It Works</a>
            </nav>

            <a href="{{ route('home') }}" class="va-logo text-center shrink-0 lg:absolute lg:left-1/2 lg:-translate-x-1/2">
                VYOMIKA<span>ATELIER</span>
            </a>

            <div class="flex items-center gap-6 lg:gap-8 flex-1 justify-end min-w-0">
                <a href="{{ request()->routeIs('home') ? '#information' : route('home') . '#information' }}" class="va-nav-link hidden md:block">Information</a>
                <a href="{{ route('account') }}" class="va-nav-link hidden sm:flex va-header-icon" aria-label="Account">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Account</span>
                </a>
                <a href="{{ route('cart.index') }}" class="va-nav-link va-header-icon" aria-label="Cart">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 01-8 0"/></svg>
                    <span class="hidden sm:inline">Cart</span>
                    @if($cartCount > 0)<span class="va-cart-badge">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>
    </header>

    <div id="va-mobile-nav" class="fixed inset-0 z-[60] text-white flex flex-col overflow-y-auto">
        <button id="va-menu-close" class="absolute top-8 right-8 text-white/60 hover:text-white" aria-label="Close">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="flex flex-col items-center gap-4 text-[0.65rem] tracking-[0.22em] uppercase py-24 px-8">
            <a href="{{ route('services.index') }}" class="text-white text-base font-serif tracking-wide normal-case mb-2">Services</a>
            <a href="{{ route('shop.index') }}" class="text-white/50">Shop</a>
            <a href="{{ route('blog.index') }}" class="text-white/50">Blog</a>
            <a href="{{ route('home') }}#how-it-works" class="text-white/50">How It Works</a>
            <a href="{{ route('home') }}#information" class="text-white/50">Information</a>
            <div class="h-px w-12 bg-white/20 my-3"></div>
            <a href="{{ route('projects.index') }}" class="text-white/50">Projects</a>
            <a href="{{ route('about') }}" class="text-white/50">About</a>
            <a href="{{ route('contact.index') }}" class="text-white/50">Contact</a>
            <div class="h-px w-12 bg-white/20 my-3"></div>
            <a href="{{ route('account') }}" class="text-white/50">Account</a>
            <a href="{{ route('cart.index') }}" class="text-white/50">Cart ({{ $cartCount }})</a>
        </div>
    </div>

    @if(session('success'))
        <div class="fixed top-0 left-0 right-0 z-[110] bg-brand-900 text-white text-center py-3 text-xs tracking-[0.15em]">{{ session('success') }}</div>
    @endif

    <main>@yield('content')</main>

    <footer class="va-footer-luxe" id="site-footer">
        <div class="max-w-[90rem] mx-auto px-5 lg:px-10 py-16 grid sm:grid-cols-2 lg:grid-cols-4 gap-10">
            <div class="sm:col-span-2 lg:col-span-1">
                <p class="va-logo mb-5">VYOMIKA<span>ATELIER</span></p>
                <p class="text-sm leading-relaxed font-light text-white/50 max-w-xs">Precision architectural metalwork and curated interiors — engineered for spaces that endure.</p>
            </div>
            <div>
                <p class="va-eyebrow mb-5 text-brand-500">Navigate</p>
                <div class="space-y-2.5 text-sm font-light">
                    <a href="{{ route('shop.index') }}" class="block hover:text-white transition">Shop</a>
                    <a href="{{ route('services.index') }}" class="block hover:text-white transition">Services</a>
                    <a href="{{ route('blog.index') }}" class="block hover:text-white transition">Blog</a>
                    <a href="{{ route('home') }}#how-it-works" class="block hover:text-white transition">How It Works</a>
                    <a href="{{ route('home') }}#information" class="block hover:text-white transition">Information</a>
                </div>
            </div>
            <div>
                <p class="va-eyebrow mb-5 text-brand-500">Studio</p>
                <div class="space-y-2.5 text-sm font-light">
                    <a href="{{ route('projects.index') }}" class="block hover:text-white transition">Projects</a>
                    <a href="{{ route('about') }}" class="block hover:text-white transition">About</a>
                    <a href="{{ route('contact.index') }}" class="block hover:text-white transition">Contact</a>
                    <a href="{{ route('leads.create') }}" class="block hover:text-white transition">Custom Order</a>
                </div>
            </div>
            <div>
                <p class="va-eyebrow mb-5 text-brand-500">Account</p>
                <div class="space-y-2.5 text-sm font-light">
                    <a href="{{ route('account') }}" class="block hover:text-white transition">My Account</a>
                    <a href="{{ route('cart.index') }}" class="block hover:text-white transition">Cart ({{ $cartCount }})</a>
                    <a href="{{ route('checkout.index') }}" class="block hover:text-white transition">Checkout</a>
                </div>
                <p class="text-sm mt-6 font-light text-white/40">hello@vyomikaatelier.com</p>
            </div>
        </div>
        <div class="border-t border-white/10 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 max-w-[90rem] mx-auto px-5 lg:px-10 text-[0.55rem] tracking-[0.28em] uppercase text-white/30">
            <span>&copy; {{ date('Y') }} VYOMIKA ATELIER</span>
            <span>Architectural Metal · India</span>
        </div>
    </footer>

    <x-order-modal />

    <script src="{{ asset('js/calculator.js') }}"></script>
    <script src="{{ asset('js/motion.js') }}"></script>
    <script>
        const btn = document.getElementById('va-menu-btn');
        const close = document.getElementById('va-menu-close');
        const nav = document.getElementById('va-mobile-nav');
        btn?.addEventListener('click', () => { nav.classList.add('open'); document.body.classList.add('va-menu-open'); });
        close?.addEventListener('click', () => { nav.classList.remove('open'); document.body.classList.remove('va-menu-open'); });
    </script>
    @stack('scripts')
</body>
</html>
