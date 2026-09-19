<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Staff & Roles') — VYOMIKA ATELIER</title>
    <link rel="stylesheet" href="{{ asset('css/admin-invitation-reveal.css') }}">
</head>
<body class="secure-staff">
    <div class="secure-shell">
        @yield('content')
    </div>
    <script src="{{ asset('js/admin-invitation-reveal.js') }}" defer></script>
</body>
</html>
