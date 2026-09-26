@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $archivedView ? 'Archived test orders' : 'Orders' }}</h1>

@if(auth()->user()?->hasAdminPermission(\App\Support\AdminRole::ORDERS_ARCHIVE_TEST))
    <p class="mb-4 text-sm">
        @if($archivedView)
            <a href="{{ route('admin.orders.index') }}" class="text-blue-600">Active orders</a>
        @else
            <a href="{{ route('admin.orders.index', ['archived' => 1]) }}" class="text-blue-600">Archived test orders</a>
        @endif
    </p>
@endif

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Order</th><th class="p-3">Customer</th><th class="p-3">Total</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($orders as $order)
        <tr class="border-b">
            <td class="p-3">{{ $order->order_number }}</td>
            <td class="p-3">{{ $order->customer_name }}</td>
            <td class="p-3">₹{{ number_format($order->total, 0) }}</td>
            <td class="p-3">
                @if($order->needsPaymentReview())
                    <strong>Reconciliation required</strong>
                @else
                    {{ $order->customerStatusLabel() }}
                @endif
                @if($order->hasAdminArchiveMetadata())
                    <strong class="block mt-1">Archived test order</strong>
                @endif
            </td>
            <td class="p-3 align-top">
                <a href="{{ route('admin.orders.show', $order) }}" class="text-blue-600">View</a>
                @if($archivedView)
                    <form method="POST" action="{{ route('admin.orders.test-archive.destroy', $order) }}" class="mt-3 space-y-2">
                        @csrf
                        <label class="block text-xs" for="unarchive_number_{{ $order->id }}">Type the order number</label>
                        <input id="unarchive_number_{{ $order->id }}" name="order_number_confirmation" class="border px-2 py-1 rounded w-full" autocomplete="off">
                        <label class="block text-xs" for="unarchive_password_{{ $order->id }}">Current password</label>
                        <input id="unarchive_password_{{ $order->id }}" type="password" name="current_password" required autocomplete="current-password" class="border px-2 py-1 rounded w-full">
                        <button type="submit" class="bg-stone-800 text-white px-3 py-1 rounded text-xs">Unarchive</button>
                    </form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
