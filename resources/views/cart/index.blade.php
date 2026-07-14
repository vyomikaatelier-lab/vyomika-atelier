@extends('layouts.app')

@section('title', 'Your Bag — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Shopping</p>
    <h1 class="font-serif text-5xl text-brand-900">Your Bag</h1>
</div>

<div class="max-w-3xl mx-auto px-5 py-16">
    @if($items->isEmpty())
        <div class="text-center py-16">
            <p class="font-serif text-2xl text-brand-400 mb-6">Your bag is empty</p>
            <a href="{{ route('shop.index') }}" class="va-btn-outline">Continue Shopping</a>
        </div>
    @else
        <div class="space-y-8">
            @foreach($items as $item)
            <div class="flex gap-6 border-b border-brand-200 pb-8">
                <div class="w-24 h-32 bg-brand-100 shrink-0 overflow-hidden">
                    @if($item['product']->imageUrl())
                        <img src="{{ $item['product']->imageUrl() }}" alt="" class="w-full h-full object-cover">
                    @endif
                </div>
                <div class="flex-1">
                    <h3 class="font-serif text-xl">{{ $item['product']->name }}</h3>
                    <p class="text-brand-500 text-sm mt-1">{{ $item['product']->formattedPrice() }}</p>
                    <form action="{{ route('cart.update', $item['product']) }}" method="POST" class="flex items-center gap-4 mt-4">
                        @csrf @method('PATCH')
                        <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" class="va-input w-16 text-center text-sm">
                        <button type="submit" class="text-[10px] uppercase tracking-[0.2em] text-brand-400 hover:text-brand-900">Update</button>
                    </form>
                </div>
                <div class="text-right">
                    <p class="font-medium">₹{{ number_format($item['line_total'], 0) }}</p>
                    <form action="{{ route('cart.remove', $item['product']) }}" method="POST" class="mt-3">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-[10px] uppercase tracking-[0.2em] text-red-400 hover:text-red-600">Remove</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-10 flex flex-col items-end gap-4">
            <p class="font-serif text-2xl">Subtotal: ₹{{ number_format($subtotal, 0) }}</p>
            <a href="{{ route('checkout.index') }}" class="va-btn-primary">Proceed to Checkout</a>
        </div>
    @endif
</div>
@endsection
