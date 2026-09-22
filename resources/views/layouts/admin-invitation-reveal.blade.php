<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Staff Invitations') — VYOMIKA ATELIER</title>
    @php
        $invitationRevealCssVer = @filemtime(public_path('css/admin-invitation-reveal.css')) ?: time();
        $invitationRevealJsVer = @filemtime(public_path('js/admin-invitation-reveal.js')) ?: time();
    @endphp
    <link rel="stylesheet" href="{{ asset('css/admin-invitation-reveal.css') }}?v={{ $invitationRevealCssVer }}">
</head>
<body class="secure-staff" data-staff-index-url="{{ route('admin.staff.invitations.index') }}">
    <div class="secure-shell">
        @yield('content')
    </div>
    <script src="{{ asset('js/admin-invitation-reveal.js') }}?v={{ $invitationRevealJsVer }}" defer></script>
</body>
</html>
