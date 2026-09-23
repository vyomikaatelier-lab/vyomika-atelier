<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') — VYOMIKA ATELIER</title>
    @if(empty($manualInvitation))
    <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900{{ !empty($manualInvitation) ? ' invitation-inline-reveal' : '' }}"@if(!empty($manualInvitation)) data-staff-index-url="{{ route('admin.staff.invitations.index') }}"@endif>
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
            <p class="admin-brand font-semibold mb-6 text-sm tracking-wider">VYOMIKA ADMIN</p>
            <nav class="admin-nav space-y-4 text-sm">
                <div class="admin-nav-section">
                    <p class="admin-nav-label text-xs uppercase text-gray-500 mb-1">Store</p>
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Dashboard</a>
                    <a href="{{ route('admin.products.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Categories</a>
                    <a href="{{ route('admin.orders.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Orders</a>
                </div>
                <div class="admin-nav-section">
                    <p class="admin-nav-label text-xs uppercase text-gray-500 mb-1">Content</p>
                    <a href="{{ route('admin.projects.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Projects</a>
                    <a href="{{ route('admin.blog.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Blog</a>
                    <a href="{{ route('admin.exhibitions.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Exhibitions</a>
                    <a href="{{ route('admin.services.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Services</a>
                    <a href="{{ route('admin.collection-pages.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Collection Pages</a>
                    <a href="{{ route('admin.page-heroes.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Page Heroes</a>
                    <a href="{{ route('admin.independent-pages.edit', 'railings') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Railings Page</a>
                    <a href="{{ route('admin.independent-pages.edit', 'corten-steel') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Corten Steel Page</a>
                    <a href="{{ route('admin.static-pages.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Static Pages SEO</a>
                    <a href="{{ route('admin.redirects.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">URL Redirects</a>
                    <a href="{{ route('admin.legal.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Legal Pages</a>
                    <a href="{{ route('admin.media.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Media</a>
                </div>
                <div class="admin-nav-section">
                    <p class="admin-nav-label text-xs uppercase text-gray-500 mb-1">Enquiries</p>
                    <a href="{{ route('admin.leads.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Leads</a>
                    <a href="{{ route('admin.professional-applications.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Professional Apps</a>
                    <a href="{{ route('admin.railing-quotes.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Railing Quotes</a>
                </div>
                <div class="admin-nav-section">
                    <p class="admin-nav-label text-xs uppercase text-gray-500 mb-1">People & Settings</p>
                    <a href="{{ route('admin.customers.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Customers</a>
                    <a href="{{ route('admin.settings.edit') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Site Settings</a>
                    <a href="{{ route('admin.mfa.manage') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">MFA / 2FA</a>
                    <a href="{{ route('admin.passkeys.manage') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800">Passkeys</a>
                    @if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_VIEW))
                        <a href="{{ route('admin.staff.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800{{ request()->routeIs('admin.staff.index') ? ' bg-gray-800' : '' }}" @if(request()->routeIs('admin.staff.index')) aria-current="page" @endif>Staff & Roles</a>
                    @endif
                    @if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::STAFF_MANAGE))
                        @php($invitationsNavCurrent = request()->routeIs('admin.staff.invitations.index') || !empty($manualInvitation))
                        <a href="{{ route('admin.staff.invitations.index') }}" class="admin-nav-link block py-1.5 px-3 rounded hover:bg-gray-800{{ $invitationsNavCurrent ? ' bg-gray-800' : '' }}" @if($invitationsNavCurrent) aria-current="page" @endif>Staff Invitations</a>
                    @endif
                </div>
                <a href="{{ route('home') }}" class="admin-nav-link admin-nav-link-muted block py-1.5 px-3 rounded hover:bg-gray-800 text-gray-400" target="_blank">View Site</a>
                <form action="{{ route('admin.logout') }}" method="POST" class="admin-logout pt-2">
                    @csrf
                    <button type="submit" class="admin-logout-button text-gray-400 hover:text-white text-sm min-h-[44px]">Logout</button>
                </form>
            </nav>
        </aside>
        @endif
        <main class="admin-main flex-1 p-8">
            @if($adminPanelActive)
            <button type="button" class="admin-menu-btn mb-4" id="admin-menu-toggle" aria-expanded="false" aria-controls="admin-sidebar">Menu</button>
            @endif
            @if(session('info'))
                <div class="admin-flash admin-flash-info bg-blue-100 text-blue-800 px-4 py-2 rounded mb-4 text-sm">{{ session('info') }}</div>
            @endif
            @if(session('success'))
                <div class="admin-flash admin-flash-success bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="admin-flash admin-flash-error bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="admin-flash admin-flash-error bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">
                    <p class="font-medium mb-1">Please fix the following errors:</p>
                    <ul class="list-disc pl-4">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <script src="{{ asset('js/responsive.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
