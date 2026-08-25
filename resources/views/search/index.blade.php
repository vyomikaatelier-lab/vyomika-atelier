@extends('layouts.store')

@section('title', ($q !== '' ? 'Search: '.$q : 'Search').' — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', [
    'title' => $q !== '' ? 'Search results' : 'Search',
    'subtitle' => $q !== '' ? 'Showing shop products matching “'.$q.'”.' : 'Search shop collections by name or SKU.',
    'showLabel' => false,
])

<section class="am-page-body">
    <div class="am-container">
        <form method="GET" action="{{ route('search') }}" class="am-shop-search" style="max-width:32rem;margin-bottom:2rem">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search products…" class="am-input" aria-label="Search products">
            <button type="submit" class="am-btn am-btn--primary am-btn--sm">Search</button>
        </form>

        @if($q === '')
            <p class="am-empty">Enter a search term to find shop products.</p>
        @elseif($products->isEmpty())
            <div class="am-empty">
                <h3>No products found</h3>
                <p>Try another search, or browse a shop collection.</p>
                <a href="{{ \App\Support\StorefrontRoutes::primaryShopUrl() }}" class="am-btn am-btn--outline">Browse Mirror Frames</a>
            </div>
        @else
            <p class="am-shop-results">{{ $products->total() }} product{{ $products->total() === 1 ? '' : 's' }}</p>
            <div class="am-product-grid am-product-grid--shop">
                @foreach($products as $product)
                    @include('partials.am-product-card', ['product' => $product])
                @endforeach
            </div>
            <div class="am-pagination">{{ $products->links('vendor.pagination.amerce') }}</div>
        @endif
    </div>
</section>
@endsection
