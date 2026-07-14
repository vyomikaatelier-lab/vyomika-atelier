<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'VYOMIKA ATELIER')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        serif: ['Cormorant Garamond', 'serif'],
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#faf8f5',
                            100: '#f3efe8',
                            200: '#e5ddd0',
                            500: '#8b7355',
                            700: '#5c4a38',
                            900: '#2d2419',
                        }
                    }
                }
            }
        }
    </script>
    @stack('styles')
</head>
<body class="font-sans text-brand-900 bg-brand-50 antialiased">
    <header class="border-b border-brand-200 bg-white/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-serif text-2xl tracking-widest text-brand-900">VYOMIKA ATELIER</a>
            <nav class="hidden md:flex items-center gap-8 text-sm uppercase tracking-wider">
                <a href="{{ route('shop.index') }}" class="hover:text-brand-500 transition">Shop</a>
                <a href="{{ route('leads.create') }}" class="hover:text-brand-500 transition">Custom Order</a>
                <a href="{{ route('about') }}" class="hover:text-brand-500 transition">About</a>
                <a href="{{ route('contact.index') }}" class="hover:text-brand-500 transition">Contact</a>
            </nav>
            <a href="{{ route('cart.index') }}" class="text-sm uppercase tracking-wider hover:text-brand-500 transition">
                Cart ({{ app(\App\Services\CartService::class)->count() }})
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="bg-green-50 border-b border-green-200 text-green-800 text-center py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-b border-red-200 text-red-800 text-center py-3 text-sm">{{ session('error') }}</div>
    @endif

    <main>@yield('content')</main>

    <footer class="border-t border-brand-200 mt-20 py-12 bg-white">
        <div class="max-w-6xl mx-auto px-4 grid md:grid-cols-3 gap-8 text-sm text-brand-700">
            <div>
                <p class="font-serif text-xl text-brand-900 mb-2">VYOMIKA ATELIER</p>
                <p class="text-brand-500">Handcrafted elegance for the discerning.</p>
            </div>
            <div>
                <p class="font-medium text-brand-900 mb-2">Explore</p>
                <div class="space-y-1">
                    <a href="{{ route('shop.index') }}" class="block hover:text-brand-500">Shop</a>
                    <a href="{{ route('leads.create') }}" class="block hover:text-brand-500">Custom Orders</a>
                    <a href="{{ route('contact.index') }}" class="block hover:text-brand-500">Contact</a>
                </div>
            </div>
            <div>
                <p class="font-medium text-brand-900 mb-2">Connect</p>
                <p>hello@vyomikaatelier.com</p>
            </div>
        </div>
        <p class="text-center text-xs text-brand-500 mt-8">&copy; {{ date('Y') }} VYOMIKA ATELIER. All rights reserved.</p>
    </footer>
</body>
</html>
