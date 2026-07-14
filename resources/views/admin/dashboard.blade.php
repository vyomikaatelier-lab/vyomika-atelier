@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="text-2xl font-semibold mb-8">Dashboard</h1>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Products</p><p class="text-2xl font-semibold">{{ $stats['products'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Orders</p><p class="text-2xl font-semibold">{{ $stats['orders'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">New Leads</p><p class="text-2xl font-semibold">{{ $stats['new_leads'] }}</p></div>
    <div class="bg-white p-5 rounded-lg shadow"><p class="text-sm text-gray-500">Revenue</p><p class="text-2xl font-semibold">₹{{ number_format($stats['revenue'], 0) }}</p></div>
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
