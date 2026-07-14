@extends('layouts.app')

@section('title', 'Checkout — VYOMIKA ATELIER')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="font-serif text-4xl mb-8">Checkout</h1>

    <form action="{{ route('checkout.store') }}" method="POST" class="grid md:grid-cols-2 gap-12">
        @csrf
        <div class="space-y-4">
            <h2 class="font-medium text-lg mb-4">Shipping Details</h2>
            <input type="text" name="customer_name" value="{{ old('customer_name') }}" placeholder="Full Name" required class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
            <input type="email" name="customer_email" value="{{ old('customer_email') }}" placeholder="Email" required class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
            <input type="tel" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Phone" required class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
            <textarea name="shipping_address" placeholder="Shipping Address" required rows="3" class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">{{ old('shipping_address') }}</textarea>
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="city" value="{{ old('city') }}" placeholder="City" required class="border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
                <input type="text" name="pincode" value="{{ old('pincode') }}" placeholder="Pincode" required class="border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
            </div>
            <input type="text" name="state" value="{{ old('state') }}" placeholder="State (optional)" class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">
            <textarea name="notes" placeholder="Order notes (optional)" rows="2" class="w-full border border-brand-200 px-4 py-2.5 bg-white focus:outline-none focus:border-brand-500">{{ old('notes') }}</textarea>

            <h2 class="font-medium text-lg mt-6 mb-2">Payment Method</h2>
            <label class="flex items-center gap-2 py-2"><input type="radio" name="payment_method" value="cod" checked> Cash on Delivery</label>
            <label class="flex items-center gap-2 py-2"><input type="radio" name="payment_method" value="bank_transfer"> Bank Transfer</label>
            @if($razorpayEnabled)
            <label class="flex items-center gap-2 py-2"><input type="radio" name="payment_method" value="razorpay"> Online Payment (Razorpay)</label>
            @endif
        </div>

        <div class="bg-white border border-brand-200 p-6 h-fit">
            <h2 class="font-medium text-lg mb-4">Order Summary</h2>
            @foreach($items as $item)
                <div class="flex justify-between text-sm py-2 border-b border-brand-100">
                    <span>{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
                    <span>₹{{ number_format($item['line_total'], 0) }}</span>
                </div>
            @endforeach
            <div class="flex justify-between py-2 text-sm"><span>Subtotal</span><span>₹{{ number_format($subtotal, 0) }}</span></div>
            <div class="flex justify-between py-2 text-sm"><span>Shipping</span><span>{{ $shipping > 0 ? '₹'.number_format($shipping, 0) : 'Free' }}</span></div>
            <div class="flex justify-between py-3 font-medium text-lg border-t border-brand-200 mt-2"><span>Total</span><span>₹{{ number_format($total, 0) }}</span></div>
            <button type="submit" class="w-full bg-brand-900 text-white py-3 mt-4 text-sm uppercase tracking-wider hover:bg-brand-700 transition">Place Order</button>
        </div>
    </form>
</div>
@endsection
