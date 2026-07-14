@extends('layouts.app')

@section('title', 'VYOMIKA ATELIER — Handcrafted Fashion')

@section('content')

{{-- Full-screen hero --}}
<section class="va-hero text-white">
    <div class="va-hero-inner max-w-7xl mx-auto px-5 w-full">
        <div class="max-w-2xl va-animate">
            <p class="va-label text-brand-200 mb-5">Bespoke &amp; Ready-to-Wear</p>
            <h1 class="font-serif text-5xl md:text-7xl lg:text-8xl leading-[1.05] mb-6">
                Crafted with<br><em class="font-light italic">intention.</em>
            </h1>
            <p class="text-brand-100 text-base md:text-lg font-light leading-relaxed mb-10 max-w-md">
                Discover curated collections of artisanal fashion, or commission a piece designed exclusively for your story.
            </p>
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="{{ route('shop.index') }}" class="va-btn-primary">Explore Collection</a>
                <a href="{{ route('leads.create') }}" class="va-btn-outline border-white text-white hover:bg-white hover:text-brand-900">Commission a Piece</a>
            </div>
        </div>
    </div>
</section>

{{-- Brand statement --}}
<section class="max-w-7xl mx-auto px-5 py-24 grid md:grid-cols-2 gap-16 items-center">
    <div>
        <div class="va-rule mb-6"></div>
        <h2 class="font-serif text-4xl md:text-5xl text-brand-900 mb-6 leading-tight">The art of<br>slow fashion</h2>
        <p class="text-brand-700 leading-relaxed mb-4">At VYOMIKA ATELIER, every stitch tells a story. We believe clothing should be treasured — not disposable. Our pieces are designed to transcend seasons and trends.</p>
        <p class="text-brand-500 leading-relaxed">From hand-selected fabrics to meticulous finishing, we honour the craft of making something truly beautiful.</p>
        <a href="{{ route('about') }}" class="inline-block mt-8 text-[11px] uppercase tracking-[0.2em] text-brand-500 border-b border-brand-500 pb-0.5 hover:text-brand-900 hover:border-brand-900 transition">Our Story</a>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <img src="https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=600&q=80" alt="Craftsmanship" class="w-full aspect-[3/4] object-cover">
        <img src="https://images.unsplash.com/photo-1617019682535-4f4fa78ce2a8?w=600&q=80" alt="Fabric detail" class="w-full aspect-[3/4] object-cover mt-8">
    </div>
</section>

@if($featuredProducts->isNotEmpty())
<section class="bg-white py-24">
    <div class="max-w-7xl mx-auto px-5">
        <div class="text-center mb-16">
            <p class="va-label mb-3">Curated Selection</p>
            <h2 class="font-serif text-4xl text-brand-900">Featured Pieces</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12">
            @foreach($featuredProducts as $product)
            <a href="{{ route('shop.show', $product->slug) }}" class="va-card group">
                <div class="aspect-[3/4] bg-brand-100 overflow-hidden mb-5">
                    @if($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-serif text-5xl text-brand-200">V</div>
                    @endif
                </div>
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mb-1">{{ $product->category?->name ?? 'Collection' }}</p>
                <h3 class="font-serif text-xl text-brand-900 group-hover:text-brand-500 transition">{{ $product->name }}</h3>
                <p class="text-brand-500 mt-1">{{ $product->formattedPrice() }}</p>
            </a>
            @endforeach
        </div>
        <div class="text-center mt-14">
            <a href="{{ route('shop.index') }}" class="va-btn-outline">View All Pieces</a>
        </div>
    </div>
</section>
@endif

{{-- Lookbook strip --}}
<section class="py-2">
    <div class="va-lookbook">
        <img src="https://images.unsplash.com/photo-1583391734527-7e944b2cbdce?w=500&q=80" alt="" class="aspect-square object-cover w-full">
        <img src="https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=500&q=80" alt="" class="aspect-square object-cover w-full">
        <img src="https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=500&q=80" alt="" class="aspect-square object-cover w-full">
        <img src="https://images.unsplash.com/photo-1601924994987-69f26d75c78e?w=500&q=80" alt="" class="aspect-square object-cover w-full">
    </div>
</section>

{{-- Bespoke CTA --}}
<section class="relative py-32 px-5 text-center text-white"
    style="background: linear-gradient(rgba(45,36,25,0.7), rgba(45,36,25,0.7)), url('https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=1600&q=80') center/cover;">
    <p class="va-label text-brand-200 mb-4">Made for You</p>
    <h2 class="font-serif text-4xl md:text-6xl mb-6">Bespoke Commissions</h2>
    <p class="text-brand-100 max-w-lg mx-auto mb-10 leading-relaxed font-light">Wedding lehengas, occasion wear, or everyday elegance — tell us your vision and our artisans will create something extraordinary.</p>
    <a href="{{ route('leads.create') }}" class="va-btn-primary">Begin Your Commission</a>
</section>

{{-- Categories --}}
@if($categories->isNotEmpty())
<section class="max-w-7xl mx-auto px-5 py-24">
    <div class="text-center mb-14">
        <p class="va-label mb-3">Browse By</p>
        <h2 class="font-serif text-4xl">Collections</h2>
    </div>
    <div class="grid sm:grid-cols-3 gap-6">
        @foreach($categories as $category)
        <a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="group relative overflow-hidden aspect-[4/5] bg-brand-100">
            <div class="absolute inset-0 bg-brand-900/20 group-hover:bg-brand-900/40 transition duration-500"></div>
            <div class="absolute inset-0 flex flex-col items-center justify-center text-white">
                <h3 class="font-serif text-2xl tracking-wide">{{ $category->name }}</h3>
                <span class="text-[10px] uppercase tracking-[0.3em] mt-2 opacity-0 group-hover:opacity-100 transition">Shop →</span>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif

@endsection
