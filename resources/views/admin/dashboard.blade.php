@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-semibold mb-8">Dashboard</h1>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Products</p><p class="text-2xl font-semibold">{{ $stats['products'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Categories</p><p class="text-2xl font-semibold">{{ $stats['categories'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Orders</p><p class="text-2xl font-semibold">{{ $stats['orders'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Revenue</p><p class="text-2xl font-semibold">₹{{ number_format($stats['revenue'], 0) }}</p></div>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <a href="{{ route('admin.projects.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Projects</p><p class="text-2xl font-semibold">{{ $stats['projects'] }}</p></a>
    <a href="{{ route('admin.blog.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Blog posts</p><p class="text-2xl font-semibold">{{ $stats['blog_posts'] }}</p></a>
    <a href="{{ route('admin.exhibitions.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Exhibitions</p><p class="text-2xl font-semibold">{{ $stats['exhibitions'] }}</p></a>
    <a href="{{ route('admin.media.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Media files</p><p class="text-2xl font-semibold">{{ $stats['media_files'] }}</p></a>
    <a href="{{ route('admin.leads.index', ['status' => 'new']) }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">New leads</p><p class="text-2xl font-semibold">{{ $stats['new_leads'] }}</p></a>
    <a href="{{ route('admin.leads.index', ['priority' => 'hot']) }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-amber-200"><p class="text-sm text-gray-500">Hot leads</p><p class="text-2xl font-semibold text-amber-700">{{ $stats['hot_leads'] }}</p></a>
    <a href="{{ route('admin.leads.index', ['follow_up' => 'overdue']) }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-red-200"><p class="text-sm text-gray-500">Overdue follow-ups</p><p class="text-2xl font-semibold text-red-700">{{ $stats['overdue_followups'] }}</p></a>
    <a href="{{ route('admin.leads.index', ['enquiry_type' => 'vendor_marketing']) }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Vendor / marketing</p><p class="text-2xl font-semibold">{{ $stats['vendor_leads'] }}</p></a>
    <a href="{{ route('admin.professional-applications.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">New professional apps</p><p class="text-2xl font-semibold">{{ $stats['professional_applications'] }}</p></a>
    <a href="{{ route('admin.railing-quotes.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">New railing quotes</p><p class="text-2xl font-semibold">{{ $stats['railing_quotes'] }}</p></a>
    <a href="{{ route('admin.customers.index') }}" class="bg-white p-5 rounded-lg shadow hover:ring-2 hover:ring-gray-200"><p class="text-sm text-gray-500">Customers</p><p class="text-2xl font-semibold">{{ $stats['customers'] }}</p></a>
</div>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-medium mb-4">Recent Orders</h2>
        @forelse($recentOrders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between py-2 border-b text-sm hover:text-blue-600">
                <span>{{ $order->order_number }}</span>
                <span>₹{{ number_format($order->total, 0) }}</span>
            </a>
        @empty
            <p class="text-gray-500 text-sm">No orders yet.</p>
        @endforelse
    </div>
    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-medium mb-4">Recent Leads</h2>
        @forelse($recentLeads as $lead)
            <a href="{{ route('admin.leads.show', $lead) }}" class="block py-2 border-b text-sm hover:text-blue-600">
                <span class="font-medium">{{ $lead->name }}</span> — {{ $lead->typeLabel() }}
            </a>
        @empty
            <p class="text-gray-500 text-sm">No leads yet.</p>
        @endforelse
    </div>
</div>
@endsection
