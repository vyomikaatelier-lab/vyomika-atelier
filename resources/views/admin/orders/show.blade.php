@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
<h1 class="text-2xl font-semibold mb-6">Order {{ $order->order_number }}</h1>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="font-medium mb-4">Customer</h2>
        <p>{{ $order->customer_name }}</p>
        <p class="text-sm text-gray-600">{{ $order->customer_email }} · {{ $order->customer_phone }}</p>
        <p class="text-sm mt-2">{{ $order->shipping_address }}, {{ $order->city }} {{ $order->pincode }}</p>
        @if($order->notes)<p class="text-sm mt-2 text-gray-600">Customer notes: {{ $order->notes }}</p>@endif
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="font-medium mb-4">Update Order</h2>
        <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="space-y-3">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm mb-1">Status</label>
                <select name="status" class="border px-3 py-2 rounded w-full">
                    @foreach(['pending','paid','processing','shipped','delivered','cancelled'] as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm mb-1">Admin notes</label>
                <textarea name="admin_notes" rows="4" class="border px-3 py-2 rounded w-full text-sm">{{ old('admin_notes', $order->admin_notes) }}</textarea>
            </div>
            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Update</button>
        </form>
        <p class="mt-4 text-sm">Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</p>
        <p class="text-lg font-semibold mt-2">Total: ₹{{ number_format($order->total, 0) }}</p>
    </div>
</div>

<div class="bg-white p-6 rounded-lg shadow mt-8">
    <h2 class="font-medium mb-4">Items</h2>
    @foreach($order->items as $item)
        <div class="flex justify-between py-2 border-b text-sm gap-4">
            <div>
                <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                @if($item->finish_name)
                    <span class="block text-xs text-gray-500">Finish: {{ $item->finish_name }}</span>
                @endif
            </div>
            <span>₹{{ number_format($item->total, 0) }}</span>
        </div>
    @endforeach
</div>
@endsection
