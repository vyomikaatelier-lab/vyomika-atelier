@extends('layouts.app')

@section('title', 'VYOMIKA ATELIER — Handcrafted Fashion')

@section('content')
<section class="relative min-h-[70vh] flex items-center justify-center text-center px-4">
    <div class="max-w-3xl">
        <p class="text-sm uppercase tracking-[0.3em] text-brand-500 mb-4">Bespoke & Ready-to-Wear</p>
        <h1 class="font-serif text-5xl md:text-7xl text-brand-900 mb-6 leading-tight">Crafted with intention.<br><em class="font-normal">Worn with grace.</em></h1>
        <p class="text-brand-700 text-lg mb-10 max-w-xl mx-auto">Discover curated collections or commission a piece made uniquely for you.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('shop.index') }}" class="bg-brand-900 text-white px-8 py-3 text-sm uppercase tracking-wider hover:bg-brand-700 transition">Shop Collection</a>
            <a href="{{ route('leads.create') }}" class="border border-brand-900 text-brand-900 px-8 py-3 text-sm uppercase tracking-wider hover:bg-brand-100 transition">Request Custom Piece</a>
        </div>
    </div>
</section>

@if($featuredProducts->isNotEmpty())
<section class="max-w-6xl mx-auto px-4 py-16">
    <h2 class="font-serif text-3xl text-center mb-12">Featured Pieces</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @foreach($featuredProducts as $product)
        <a href="{{ route('shop.show', $product->slug) }}" class="group">
            <div class="aspect-[3/4] bg-brand-100 mb-4 overflow-hidden">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                @else
                    <div class="w-full h-full flex items-center justify-center text-brand-300 font-serif text-4xl">VA</div>
                @endif
            </div>
            <h3 class="font-medium">{{ $product->name }}</h3>
            <p class="text-brand-500">{{ $product->formattedPrice() }}</p>
        </a>
        @endforeach
    </div>
</section>
@endif

<section class="bg-brand-100 py-20 px-4 text-center">
    <h2 class="font-serif text-3xl mb-4">Bespoke Commissions</h2>
    <p class="text-brand-700 max-w-lg mx-auto mb-8">Every custom piece begins with a conversation. Share your vision and we will bring it to life.</p>
    <a href="{{ route('leads.create') }}" class="inline-block border border-brand-900 px-8 py-3 text-sm uppercase tracking-wider hover:bg-white transition">Start Your Custom Order</a>
</section>
@endsection
