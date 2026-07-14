@extends('layouts.app')

@section('title', $product->name . ' — VYOMIKA ATELIER')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid md:grid-cols-2 gap-12">
        <div class="aspect-[3/4] bg-brand-100">
            @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-brand-300 font-serif text-6xl">VA</div>
            @endif
        </div>
        <div>
            @if($product->category)
                <p class="text-xs uppercase tracking-wider text-brand-500 mb-2">{{ $product->category->name }}</p>
            @endif
            <h1 class="font-serif text-4xl mb-4">{{ $product->name }}</h1>
            <p class="text-2xl text-brand-700 mb-6">{{ $product->formattedPrice() }}</p>
            <p class="text-brand-700 leading-relaxed mb-8">{{ $product->description }}</p>

            @if($product->inStock())
                <form action="{{ route('cart.add', $product) }}" method="POST" class="flex gap-4 items-end">
                    @csrf
                    <div>
                        <label class="text-xs uppercase tracking-wider text-brand-500">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" class="block border border-brand-200 px-3 py-2 w-20 mt-1">
                    </div>
                    <button type="submit" class="bg-brand-900 text-white px-8 py-2.5 text-sm uppercase tracking-wider hover:bg-brand-700 transition">Add to Cart</button>
                </form>
                <p class="text-sm text-brand-500 mt-3">{{ $product->stock }} in stock</p>
            @else
                <p class="text-red-600 font-medium">Out of stock</p>
            @endif
        </div>
    </div>

    @if($related->isNotEmpty())
    <section class="mt-20">
        <h2 class="font-serif text-2xl mb-8">You may also like</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($related as $item)
            <a href="{{ route('shop.show', $item->slug) }}" class="group">
                <div class="aspect-[3/4] bg-brand-100 mb-3 overflow-hidden">
                    @if($item->imageUrl())
                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="w-full h-full object-cover group-hover:scale-105 transition">
                    @endif
                </div>
                <p class="text-sm font-medium">{{ $item->name }}</p>
                <p class="text-sm text-brand-500">{{ $item->formattedPrice() }}</p>
            </a>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection
