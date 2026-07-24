<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Admin') — VYOMIKA ATELIER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
</head>
<body class="bg-gray-50 text-gray-900">
    @php
        $adminPanelActive = auth()->check()
            && auth()->user()->isAdmin()
            && auth()->user()->is_active
            && \App\Support\AdminAccess::verified(request());
    @endphp
    <div class="admin-shell flex min-h-screen">
        @if($adminPanelActive)
        <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" aria-hidden="true"></div>
        <aside class="admin-sidebar w-60 bg-gray-900 text-white p-4 shrink-0 overflow-y-auto" id="admin-sidebar" aria-label="Admin navigation">
            <p class="font-semibold mb-6 text-sm tracking-wider">VYOMIKA ADMIN</p>
            <nav class="space-y-4 text-sm">
                <div>
                    <p class="text-xs uppercase text-gray-500 mb-1">Store</p>
                    <a href="{{ route('admin.dashboard') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Dashboard</a>
                    <a href="{{ route('admin.products.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Categories</a>
                    <a href="{{ route('admin.orders.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Orders</a>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 mb-1">Content</p>
                    <a href="{{ route('admin.projects.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Projects</a>
                    <a href="{{ route('admin.blog.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Blog</a>
                    <a href="{{ route('admin.exhibitions.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Exhibitions</a>
                    <a href="{{ route('admin.services.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Services</a>
                    <a href="{{ route('admin.collection-pages.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Collection Pages</a>
                    <a href="{{ route('admin.page-heroes.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Page Heroes</a>
                    <a href="{{ route('admin.independent-pages.edit', 'railings') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Railings Page</a>
                    <a href="{{ route('admin.independent-pages.edit', 'corten-steel') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Corten Steel Page</a>
                    <a href="{{ route('admin.static-pages.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Static Pages SEO</a>
                    <a href="{{ route('admin.redirects.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">URL Redirects</a>
                    <a href="{{ route('admin.legal.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Legal Pages</a>
                    <a href="{{ route('admin.media.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Media</a>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 mb-1">Enquiries</p>
                    <a href="{{ route('admin.leads.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Leads</a>
                    <a href="{{ route('admin.professional-applications.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Professional Apps</a>
                    <a href="{{ route('admin.railing-quotes.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Railing Quotes</a>
                </div>
                <div>
                    <p class="text-xs uppercase text-gray-500 mb-1">People & Settings</p>
                    <a href="{{ route('admin.customers.index') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Customers</a>
                    <a href="{{ route('admin.settings.edit') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800">Site Settings</a>
                </div>
                <a href="{{ route('home') }}" class="block py-1.5 px-3 rounded hover:bg-gray-800 text-gray-400" target="_blank">View Site</a>
                <form action="{{ route('admin.logout') }}" method="POST" class="pt-2">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-white text-sm min-h-[44px]">Logout</button>
                </form>
            </nav>
        </aside>
        @endif
        <main class="admin-main flex-1 p-8">
            @if($adminPanelActive)
            <button type="button" class="admin-menu-btn mb-4" id="admin-menu-toggle" aria-expanded="false" aria-controls="admin-sidebar">Menu</button>
            @endif
            @if(session('info'))
                <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded mb-4 text-sm">{{ session('info') }}</div>
            @endif
            @if(session('success'))
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">
                    <p class="font-medium mb-1">Could not save settings:</p>
                    <ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('js/responsive.js') }}" defer></script>
</body>
</html>
