@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Orders</h1>

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Order</th><th class="p-3">Customer</th><th class="p-3">Total</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($orders as $order)
        <tr class="border-b">
            <td class="p-3">{{ $order->order_number }}</td>
            <td class="p-3">{{ $order->customer_name }}</td>
            <td class="p-3">₹{{ number_format($order->total, 0) }}</td>
            <td class="p-3">{{ $order->statusLabel() }}</td>
            <td class="p-3"><a href="{{ route('admin.orders.show', $order) }}" class="text-blue-600">View</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
