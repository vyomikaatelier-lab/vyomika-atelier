@extends('layouts.app')

@section('title', 'Cart — VYOMIKA ATELIER')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="font-serif text-4xl mb-8">Your Cart</h1>

    @if($items->isEmpty())
        <p class="text-brand-500 mb-6">Your cart is empty.</p>
        <a href="{{ route('shop.index') }}" class="text-sm uppercase tracking-wider border border-brand-900 px-6 py-2 inline-block hover:bg-brand-100 transition">Continue Shopping</a>
    @else
        <div class="space-y-6">
            @foreach($items as $item)
            <div class="flex gap-6 border-b border-brand-200 pb-6">
                <div class="w-24 h-32 bg-brand-100 shrink-0">
                    @if($item['product']->imageUrl())
                        <img src="{{ $item['product']->imageUrl() }}" alt="" class="w-full h-full object-cover">
                    @endif
                </div>
                <div class="flex-1">
                    <h3 class="font-medium">{{ $item['product']->name }}</h3>
                    <p class="text-brand-500">{{ $item['product']->formattedPrice() }}</p>
                    <form action="{{ route('cart.update', $item['product']) }}" method="POST" class="flex items-center gap-4 mt-3">
                        @csrf @method('PATCH')
                        <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" class="border border-brand-200 px-2 py-1 w-16 text-sm">
                        <button type="submit" class="text-xs uppercase tracking-wider text-brand-500 hover:text-brand-900">Update</button>
                    </form>
                </div>
                <div class="text-right">
                    <p class="font-medium">₹{{ number_format($item['line_total'], 0) }}</p>
                    <form action="{{ route('cart.remove', $item['product']) }}" method="POST" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-8 text-right">
            <p class="text-xl font-medium mb-4">Subtotal: ₹{{ number_format($subtotal, 0) }}</p>
            <a href="{{ route('checkout.index') }}" class="bg-brand-900 text-white px-8 py-3 text-sm uppercase tracking-wider hover:bg-brand-700 transition inline-block">Proceed to Checkout</a>
        </div>
    @endif
</div>
@endsection
