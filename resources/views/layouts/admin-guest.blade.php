<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title', 'Admin Login') — VYOMIKA ATELIER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
</head>
<body class="bg-gray-50 text-gray-900">
    <main class="min-h-screen p-8">
        @if(session('info'))
            <div class="max-w-sm mx-auto mb-4 bg-blue-100 text-blue-800 px-4 py-2 rounded text-sm">{{ session('info') }}</div>
        @endif
        @if(session('success'))
            <div class="max-w-sm mx-auto mb-4 bg-green-100 text-green-800 px-4 py-2 rounded text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="max-w-sm mx-auto mb-4 bg-red-100 text-red-800 px-4 py-2 rounded text-sm">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
