@extends('layouts.app')

@section('title', $product->name . ' — VYOMIKA ATELIER')

@section('content')
<div class="max-w-7xl mx-auto px-5 py-12">
    <div class="grid lg:grid-cols-2 gap-12 lg:gap-20">
        <div class="aspect-[3/4] bg-brand-100 overflow-hidden">
            @if($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center font-serif text-8xl text-brand-200">V</div>
            @endif
        </div>
        <div class="flex flex-col justify-center py-8">
            @if($product->category)
                <p class="va-label mb-3">{{ $product->category->name }}</p>
            @endif
            <h1 class="font-serif text-4xl md:text-5xl text-brand-900 mb-4">{{ $product->name }}</h1>
            <p class="text-2xl text-brand-700 mb-8">{{ $product->formattedPrice() }}</p>
            <div class="va-rule mb-8"></div>
            <p class="text-brand-700 leading-relaxed mb-10">{{ $product->description }}</p>

            @if($product->inStock())
                <form action="{{ route('cart.add', $product) }}" method="POST" class="flex flex-wrap gap-4 items-end">
                    @csrf
                    <div>
                        <label class="va-label block mb-2">Qty</label>
                        <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" class="va-input w-20 text-center">
                    </div>
                    <button type="submit" class="va-btn-primary">Add to Bag</button>
                </form>
                <p class="text-xs text-brand-400 mt-4 tracking-wide">{{ $product->stock }} available</p>
            @else
                <p class="text-red-700 font-medium tracking-wide">Currently unavailable</p>
            @endif
        </div>
    </div>

    @if($related->isNotEmpty())
    <section class="mt-24 pt-16 border-t border-brand-200">
        <p class="va-label text-center mb-3">You May Also Like</p>
        <h2 class="font-serif text-3xl text-center mb-12">Related Pieces</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($related as $item)
            <a href="{{ route('shop.show', $item->slug) }}" class="va-card group">
                <div class="aspect-[3/4] bg-brand-100 overflow-hidden mb-3">
                    @if($item->imageUrl())
                        <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                    @endif
                </div>
                <p class="font-serif text-base group-hover:text-brand-500 transition">{{ $item->name }}</p>
                <p class="text-sm text-brand-500">{{ $item->formattedPrice() }}</p>
            </a>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection
