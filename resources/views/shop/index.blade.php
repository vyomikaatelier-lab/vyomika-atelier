@extends('layouts.app')

@section('title', 'Shop — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">The Collection</p>
    <h1 class="font-serif text-5xl text-brand-900">Shop</h1>
</div>

<div class="max-w-7xl mx-auto px-5 py-16">
    @php
        $furnitureSlugs = ['coffee-tables', 'corner-tables', 'glass-tables'];
        $isFurniture = in_array(request('category'), $furnitureSlugs, true);
        $categoryName = $categories->firstWhere('slug', request('category'))?->name;
    @endphp
    <div class="flex flex-col lg:flex-row gap-12">
        <aside class="lg:w-52 shrink-0">
            <p class="va-label mb-5">Category</p>
            <div class="space-y-3 text-sm">
                <a href="{{ route('shop.index') }}" class="block {{ !request('category') ? 'text-brand-900 font-medium' : 'text-brand-400 hover:text-brand-900' }} transition">All Pieces</a>
                @foreach($categories as $category)
                    <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                       class="block {{ request('category') === $category->slug ? 'text-brand-900 font-medium' : 'text-brand-400 hover:text-brand-900' }} transition">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </aside>

        <div class="flex-1">
            <form method="GET" class="mb-10">
                @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search the collection…"
                    class="va-input max-w-sm">
            </form>

            @if($products->isEmpty())
                <p class="text-brand-400 text-center py-20 font-serif text-xl">No pieces found.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-12">
                    @foreach($products as $product)
                    <a href="{{ route('shop.show', $product->slug) }}" class="va-card group">
                        <div class="aspect-[3/4] bg-brand-100 overflow-hidden mb-4">
                            @if($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-serif text-5xl text-brand-200">V</div>
                            @endif
                        </div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mb-1">{{ $product->category?->name ?? 'Piece' }}</p>
                        <h3 class="font-serif text-lg group-hover:text-brand-500 transition">{{ $product->name }}</h3>
                        <p class="text-brand-500 text-sm mt-1">{{ $product->formattedPrice() }}</p>
                    </a>
                    @endforeach
                </div>
                <div class="mt-14">{{ $products->links() }}</div>
            @endif

            @if($isFurniture)
                <div class="mt-20 bg-white border border-brand-200 p-8 md:p-10">
                    <p class="va-label mb-3">Custom Furniture</p>
                    <h2 class="font-serif text-3xl text-brand-900 mb-2">Request {{ $categoryName ?? 'Furniture' }}</h2>
                    <p class="text-sm text-brand-500 mb-8">Need a custom size, material, or finish? Send us your requirements and we'll prepare a quote.</p>
                    <x-lead-form-inline
                        :service-slug="request('category')"
                        :subject="($categoryName ?? 'Furniture') . ' enquiry'"
                        type="service_inquiry" />
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
