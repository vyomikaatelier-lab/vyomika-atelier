@extends('layouts.app')

@section('title', 'Checkout — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Secure Checkout</p>
    <h1 class="font-serif text-5xl text-brand-900">Checkout</h1>
</div>

<div class="max-w-5xl mx-auto px-5 py-16">
    <form action="{{ route('checkout.store') }}" method="POST" class="grid lg:grid-cols-2 gap-16">
        @csrf
        <div class="space-y-4">
            <h2 class="font-serif text-2xl mb-4">Shipping Details</h2>
            <input type="text" name="customer_name" value="{{ old('customer_name') }}" placeholder="Full Name" required class="va-input">
            <input type="email" name="customer_email" value="{{ old('customer_email') }}" placeholder="Email" required class="va-input">
            <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Phone" required class="va-input">
            <textarea name="shipping_address" placeholder="Shipping Address" required rows="3" class="va-input">{{ old('shipping_address') }}</textarea>
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="city" value="{{ old('city') }}" placeholder="City" required class="va-input">
                <input type="text" name="pincode" value="{{ old('pincode') }}" placeholder="Pincode" required class="va-input">
            </div>
            <input type="text" name="state" value="{{ old('state') }}" placeholder="State (optional)" class="va-input">
            <textarea name="notes" placeholder="Order notes (optional)" rows="2" class="va-input">{{ old('notes') }}</textarea>

            <h2 class="font-serif text-xl mt-8 mb-3">Payment</h2>
            <label class="flex items-center gap-3 py-2 text-sm cursor-pointer">
                <input type="radio" name="payment_method" value="cod" checked> Cash on Delivery
            </label>
            <label class="flex items-center gap-3 py-2 text-sm cursor-pointer">
                <input type="radio" name="payment_method" value="bank_transfer"> Bank Transfer
            </label>
            @if($razorpayEnabled)
            <label class="flex items-center gap-3 py-2 text-sm cursor-pointer">
                <input type="radio" name="payment_method" value="razorpay"> Online Payment
            </label>
            @endif
        </div>

        <div class="bg-white border border-brand-200 p-8 h-fit">
            <h2 class="font-serif text-2xl mb-6">Order Summary</h2>
            @foreach($items as $item)
                <div class="flex justify-between text-sm py-3 border-b border-brand-100">
                    <span>{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
                    <span>₹{{ number_format($item['line_total'], 0) }}</span>
                </div>
            @endforeach
            <div class="flex justify-between py-3 text-sm text-brand-500"><span>Subtotal</span><span>₹{{ number_format($subtotal, 0) }}</span></div>
            <div class="flex justify-between py-3 text-sm text-brand-500"><span>Shipping</span><span>{{ $shipping > 0 ? '₹'.number_format($shipping, 0) : 'Free' }}</span></div>
            <div class="flex justify-between py-4 font-serif text-2xl border-t border-brand-200 mt-2"><span>Total</span><span>₹{{ number_format($total, 0) }}</span></div>
            <button type="submit" class="va-btn-primary w-full text-center mt-4">Place Order</button>
        </div>
    </form>
</div>
@endsection
