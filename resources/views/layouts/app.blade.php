<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VYOMIKA ATELIER — Bespoke fashion and curated ready-to-wear. Handcrafted with intention.">
    <title>@yield('title', 'VYOMIKA ATELIER')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400;1,600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/atelier.css') }}">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['Cormorant Garamond', 'serif'],
                        sans: ['Jost', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#faf8f5', 100: '#f3efe8', 200: '#e5ddd0',
                            400: '#9a8b7a', 500: '#8b7355', 700: '#5c4a38', 900: '#2d2419',
                        }
                    }
                }
            }
        }
    </script>
    @stack('styles')
</head>
<body class="font-sans text-brand-900 bg-brand-50 antialiased">

    {{-- Top bar --}}
    <div class="bg-brand-900 text-white text-center text-[10px] tracking-[0.25em] uppercase py-2 px-4">
        Complimentary shipping on orders above ₹5,000
    </div>

    {{-- Header --}}
    <header class="sticky top-0 z-50 bg-brand-50/95 backdrop-blur border-b border-brand-200">
        <div class="max-w-7xl mx-auto px-5 py-5 flex items-center justify-between">
            <button id="va-menu-btn" class="md:hidden text-brand-900" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <nav class="hidden md:flex items-center gap-10 text-[11px] uppercase tracking-[0.2em] flex-1">
                <a href="{{ route('shop.index') }}" class="hover:text-brand-500 transition">Shop</a>
                <a href="{{ route('leads.create') }}" class="hover:text-brand-500 transition">Bespoke</a>
            </nav>

            <a href="{{ route('home') }}" class="font-serif text-2xl md:text-3xl tracking-[0.15em] text-brand-900 text-center">
                VYOMIKA
                <span class="block text-[9px] font-sans tracking-[0.5em] text-brand-500 -mt-1">ATELIER</span>
            </a>

            <div class="flex items-center gap-6 text-[11px] uppercase tracking-[0.2em] flex-1 justify-end">
                <a href="{{ route('about') }}" class="hidden md:block hover:text-brand-500 transition">About</a>
                <a href="{{ route('contact.index') }}" class="hidden md:block hover:text-brand-500 transition">Contact</a>
                <a href="{{ route('cart.index') }}" class="hover:text-brand-500 transition">
                    Bag <span class="text-brand-500">({{ app(\App\Services\CartService::class)->count() }})</span>
                </a>
            </div>
        </div>
    </header>

    {{-- Mobile nav --}}
    <div id="va-mobile-nav" class="fixed inset-0 z-[60] bg-brand-900 text-white flex flex-col justify-center items-center gap-8 text-lg tracking-[0.2em] uppercase">
        <button id="va-menu-close" class="absolute top-6 right-6" aria-label="Close">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <a href="{{ route('shop.index') }}">Shop</a>
        <a href="{{ route('leads.create') }}">Bespoke</a>
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('contact.index') }}">Contact</a>
        <a href="{{ route('cart.index') }}">Bag</a>
    </div>

    @if(session('success'))
        <div class="bg-brand-100 border-b border-brand-200 text-brand-700 text-center py-3 text-sm tracking-wide">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-b border-red-200 text-red-800 text-center py-3 text-sm">{{ session('error') }}</div>
    @endif

    <main>@yield('content')</main>

    {{-- Footer --}}
    <footer class="bg-brand-900 text-brand-200 mt-0">
        <div class="max-w-7xl mx-auto px-5 py-16 grid md:grid-cols-4 gap-10">
            <div class="md:col-span-2">
                <p class="font-serif text-3xl text-white tracking-[0.1em] mb-3">VYOMIKA ATELIER</p>
                <p class="text-brand-400 text-sm leading-relaxed max-w-sm">Where craftsmanship meets couture. Each piece is an expression of artistry — whether from our collection or made uniquely for you.</p>
            </div>
            <div>
                <p class="va-label text-brand-500 mb-4">Navigate</p>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('shop.index') }}" class="block hover:text-white transition">Shop</a>
                    <a href="{{ route('leads.create') }}" class="block hover:text-white transition">Bespoke Orders</a>
                    <a href="{{ route('about') }}" class="block hover:text-white transition">Our Story</a>
                    <a href="{{ route('contact.index') }}" class="block hover:text-white transition">Contact</a>
                </div>
            </div>
            <div>
                <p class="va-label text-brand-500 mb-4">Connect</p>
                <p class="text-sm mb-1">hello@vyomikaatelier.com</p>
                <p class="text-sm text-brand-400">vyomikaatelier.com</p>
            </div>
        </div>
        <div class="border-t border-brand-700 py-6 text-center text-[10px] tracking-[0.2em] uppercase text-brand-500">
            &copy; {{ date('Y') }} VYOMIKA ATELIER · All Rights Reserved
        </div>
    </footer>

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
