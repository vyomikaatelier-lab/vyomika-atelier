<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Staff invitation') — VYOMIKA ATELIER</title>
    <link rel="stylesheet" href="{{ asset('css/admin-invitation-reveal.css') }}">
</head>
<body class="invitation-reveal">
    <main class="reveal-shell">
        @yield('content')
    </main>
    <script src="{{ asset('js/admin-invitation-reveal.js') }}" defer></script>
</body>
</html>
