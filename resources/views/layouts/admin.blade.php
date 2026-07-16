<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — VYOMIKA ATELIER</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="flex min-h-screen">
        @auth
        <aside class="w-60 bg-gray-900 text-white p-4 shrink-0 overflow-y-auto">
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
                    <button type="submit" class="text-gray-400 hover:text-white text-sm">Logout</button>
                </form>
            </nav>
        </aside>
        @endauth
        <main class="flex-1 p-8">
            @if(session('success'))
                <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">
                    <ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
