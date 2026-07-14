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
        <aside class="w-56 bg-gray-900 text-white p-4 shrink-0">
            <p class="font-semibold mb-6 text-sm tracking-wider">VYOMIKA ADMIN</p>
            <nav class="space-y-1 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="block py-2 px-3 rounded hover:bg-gray-800">Dashboard</a>
                <a href="{{ route('admin.products.index') }}" class="block py-2 px-3 rounded hover:bg-gray-800">Products</a>
                <a href="{{ route('admin.orders.index') }}" class="block py-2 px-3 rounded hover:bg-gray-800">Orders</a>
                <a href="{{ route('admin.leads.index') }}" class="block py-2 px-3 rounded hover:bg-gray-800">Leads</a>
                <a href="{{ route('home') }}" class="block py-2 px-3 rounded hover:bg-gray-800 text-gray-400" target="_blank">View Site</a>
                <form action="{{ route('admin.logout') }}" method="POST" class="pt-4">
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
            @yield('content')
        </main>
    </div>
</body>
</html>
