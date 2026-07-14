@extends('layouts.app')

@section('title', 'Shop — VYOMIKA ATELIER')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-12">
    <h1 class="font-serif text-4xl mb-8 text-center">Shop</h1>

    <div class="flex flex-col md:flex-row gap-8">
        <aside class="md:w-48 shrink-0">
            <p class="text-xs uppercase tracking-wider text-brand-500 mb-3">Categories</p>
            <a href="{{ route('shop.index') }}" class="block py-1 {{ !request('category') ? 'font-medium text-brand-900' : 'text-brand-500 hover:text-brand-900' }}">All</a>
            @foreach($categories as $category)
                <a href="{{ route('shop.index', ['category' => $category->slug]) }}" class="block py-1 {{ request('category') === $category->slug ? 'font-medium text-brand-900' : 'text-brand-500 hover:text-brand-900' }}">{{ $category->name }}</a>
            @endforeach
        </aside>

        <div class="flex-1">
            <form method="GET" class="mb-8">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="w-full border border-brand-200 px-4 py-2 bg-white focus:outline-none focus:border-brand-500">
            </form>

            @if($products->isEmpty())
                <p class="text-center text-brand-500 py-12">No products found.</p>
            @else
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($products as $product)
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
                <div class="mt-10">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
