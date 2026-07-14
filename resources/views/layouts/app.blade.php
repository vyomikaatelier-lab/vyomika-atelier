<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="VYOMIKA ATELIER — Partitions, Corten façades, slim profile doors, bespoke metal furniture, PVD finishes, and home decor.">
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

    <div class="bg-brand-900 text-white text-center text-[10px] tracking-[0.25em] uppercase py-2 px-4">
        Bespoke metal fabrication &amp; architectural solutions
    </div>

    <header class="sticky top-0 z-50 bg-brand-50/95 backdrop-blur border-b border-brand-200">
        <div class="max-w-7xl mx-auto px-5 py-4 flex items-center justify-between">
            <button id="va-menu-btn" class="lg:hidden text-brand-900" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <nav class="hidden lg:flex items-center gap-8 text-[10px] uppercase tracking-[0.18em] flex-1">
                <div class="va-nav-dropdown group relative">
                    <button type="button" class="flex items-center gap-1 hover:text-brand-500 transition py-2">
                        Services
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="va-dropdown-panel">
                        <a href="{{ route('services.index') }}" class="va-dropdown-title">All Services</a>
                        @foreach($navServices ?? [] as $svc)
                            <a href="{{ route('services.show', $svc->slug) }}">{{ $svc->name }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="va-nav-dropdown group relative">
                    <button type="button" class="flex items-center gap-1 hover:text-brand-500 transition py-2">
                        Products
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="va-dropdown-panel">
                        <span class="va-dropdown-label">Furniture</span>
                        @foreach($navFurnitureCategories ?? [] as $cat)
                            <a href="{{ route('shop.index', ['category' => $cat->slug]) }}">{{ $cat->name }}</a>
                        @endforeach
                        @if($navDoorHandles ?? null)
                            <span class="va-dropdown-label mt-3">Hardware</span>
                            <a href="{{ route('shop.index', ['category' => 'door-handles']) }}">Door Handles</a>
                        @endif
                        <a href="{{ route('shop.index') }}" class="va-dropdown-title mt-3">Shop All</a>
                    </div>
                </div>

                <a href="{{ route('projects.index') }}" class="hover:text-brand-500 transition">Projects</a>
                <a href="{{ route('blog.index') }}" class="hover:text-brand-500 transition">Blog</a>
            </nav>

            <a href="{{ route('home') }}" class="font-serif text-2xl md:text-3xl tracking-[0.15em] text-brand-900 text-center">
                VYOMIKA
                <span class="block text-[9px] font-sans tracking-[0.5em] text-brand-500 -mt-1">ATELIER</span>
            </a>

            <div class="flex items-center gap-6 text-[10px] uppercase tracking-[0.18em] flex-1 justify-end">
                <a href="{{ route('about') }}" class="hidden md:block hover:text-brand-500 transition">About</a>
                <a href="{{ route('contact.index') }}" class="hidden md:block hover:text-brand-500 transition">Contact</a>
                <a href="{{ route('cart.index') }}" class="hover:text-brand-500 transition">
                    Bag <span class="text-brand-500">({{ app(\App\Services\CartService::class)->count() }})</span>
                </a>
            </div>
        </div>
    </header>

    <div id="va-mobile-nav" class="fixed inset-0 z-[60] bg-brand-900 text-white flex flex-col overflow-y-auto">
        <button id="va-menu-close" class="absolute top-6 right-6" aria-label="Close">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        <div class="flex flex-col items-center gap-6 text-sm tracking-[0.2em] uppercase py-24 px-8">
            <p class="text-brand-400 text-[10px]">Services</p>
            <a href="{{ route('services.index') }}">All Services</a>
            @foreach($navServices ?? [] as $svc)
                <a href="{{ route('services.show', $svc->slug) }}" class="text-brand-200">{{ $svc->name }}</a>
            @endforeach
            <p class="text-brand-400 text-[10px] mt-4">Products</p>
            @foreach($navFurnitureCategories ?? [] as $cat)
                <a href="{{ route('shop.index', ['category' => $cat->slug]) }}">{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('shop.index', ['category' => 'door-handles']) }}">Door Handles</a>
            <p class="text-brand-400 text-[10px] mt-4">More</p>
            <a href="{{ route('projects.index') }}">Projects</a>
            <a href="{{ route('blog.index') }}">Blog</a>
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('contact.index') }}">Contact</a>
            <a href="{{ route('cart.index') }}">Bag</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-brand-100 border-b border-brand-200 text-brand-700 text-center py-3 text-sm tracking-wide">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-b border-red-200 text-red-800 text-center py-3 text-sm">{{ session('error') }}</div>
    @endif

    <main>@yield('content')</main>

    <footer class="bg-brand-900 text-brand-200 mt-0">
        <div class="max-w-7xl mx-auto px-5 py-16 grid md:grid-cols-4 gap-10">
            <div class="md:col-span-2">
                <p class="font-serif text-3xl text-white tracking-[0.1em] mb-3">VYOMIKA ATELIER</p>
                <p class="text-brand-400 text-sm leading-relaxed max-w-sm">Partitions, Corten façades, slim profile doors, bespoke metal furniture, PVD finishes, and curated home decor.</p>
            </div>
            <div>
                <p class="va-label text-brand-500 mb-4">Services</p>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('services.index') }}" class="block hover:text-white transition">All Services</a>
                    @foreach(($navServices ?? collect())->take(4) as $svc)
                        <a href="{{ route('services.show', $svc->slug) }}" class="block hover:text-white transition">{{ $svc->name }}</a>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="va-label text-brand-500 mb-4">Connect</p>
                <div class="space-y-2 text-sm">
                    <a href="{{ route('projects.index') }}" class="block hover:text-white transition">Projects</a>
                    <a href="{{ route('blog.index') }}" class="block hover:text-white transition">Blog</a>
                    <a href="{{ route('contact.index') }}" class="block hover:text-white transition">Contact</a>
                </div>
                <p class="text-sm mt-4">hello@vyomikaatelier.com</p>
            </div>
        </div>
        <div class="border-t border-brand-700 py-6 text-center text-[10px] tracking-[0.2em] uppercase text-brand-500">
            &copy; {{ date('Y') }} VYOMIKA ATELIER · All Rights Reserved
        </div>
    </footer>

    <x-order-modal />

    <script src="{{ asset('js/calculator.js') }}"></script>
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
