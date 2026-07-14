@extends('layouts.app')

@section('title', 'Order Confirmed — VYOMIKA ATELIER')

@section('content')
<div class="max-w-xl mx-auto px-4 py-20 text-center">
    <h1 class="font-serif text-4xl mb-4">Thank You</h1>
    <p class="text-brand-700 mb-2">Your order has been placed successfully.</p>
    <p class="text-2xl font-medium mb-6">Order #{{ $order->order_number }}</p>
    <p class="text-brand-500 mb-8">We'll send a confirmation to {{ $order->customer_email }}.</p>
            @if($order->payment_method === 'bank_transfer')
            <p class="text-brand-600 text-sm mt-4 max-w-md mx-auto">Bank transfer details will be sent to {{ $order->customer_email }} shortly. Your order is confirmed once payment is received.</p>
            @endif
</div>
@endsection
