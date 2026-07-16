@extends('layouts.store')

@section('title', ($pageTitle ?? 'Shop') . ' — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Products',
    'title' => $pageTitle ?? 'Shop',
    'subtitle' => $pageSubtitle ?? null,
])

@php
    use App\Models\Service;
    $calcCategories = Service::calculatorCategorySlugs();
    $showCategoryCalc = $activeCategory && in_array($activeCategory->slug, $calcCategories, true);
    $calcRate = 1800;
    $calcServiceSlug = $activeCategory ? Service::serviceSlugForCategory($activeCategory->slug) : 'partitions';
    $calcLabel = $activeCategory
        ? (new Service(['slug' => $calcServiceSlug]))->calculatorEstimateLabel()
        : 'partition';
    $categoryHeroImage = match ($activeCategory?->slug) {
        'metal-furniture' => 'https://images.unsplash.com/photo-1615529182904-896166571fac?w=800&q=80',
        'door-handles' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
        default => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
    };
@endphp

@if($showCategoryCalc)
    @include('partials.am-category-featured-calc', [
        'title' => $activeCategory->name,
        'summary' => $pageSubtitle,
        'image' => $categoryHeroImage,
        'serviceSlug' => $calcServiceSlug,
        'calcTitle' => 'Estimate your ' . $calcLabel,
        'rate' => $calcRate,
    ])
@endif

<section class="am-page-body">
    <div class="am-container">
        @php
            $quoteSlugs = ['partitions', 'fluted-panels', 'room-dividers', 'coffee-tables', 'metal-furniture'];
            $showQuote = in_array(request('category'), $quoteSlugs, true);
            $categoryName = $activeCategory?->name;
        @endphp

        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Shop', 'url' => route('shop.index')],
            ...($activeCategory ? [['label' => $activeCategory->name]] : []),
        ]])

        <div class="am-layout-shop">
            <aside class="am-shop-sidebar">
                <p class="am-sidebar-title">Category</p>
                <a href="{{ route('shop.index', request()->only('search', 'sort')) }}" class="am-sidebar-link {{ !request('category') ? 'is-active' : '' }}">All Products</a>
                @foreach($categories as $category)
                    <a href="{{ route('shop.index', array_merge(request()->only('search', 'sort'), ['category' => $category->slug])) }}"
                       class="am-sidebar-link {{ request('category') === $category->slug ? 'is-active' : '' }}">
                        {{ $category->name }}
                        <span class="am-sidebar-count">{{ $category->products_count }}</span>
                    </a>
                @endforeach
            </aside>

            <div class="am-shop-main">
                <div class="am-shop-toolbar">
                    <form method="GET" class="am-shop-search">
                        @foreach(request()->except('search', 'page') as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search products…" class="am-input">
                        <button type="submit" class="am-btn am-btn--primary am-btn--sm">Search</button>
                    </form>
                    <form method="GET" class="am-shop-sort">
                        @foreach(request()->except('sort', 'page') as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <label for="shop-sort">Sort</label>
                        <select name="sort" id="shop-sort" class="am-input am-input--select" onchange="this.form.submit()">
                            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                            <option value="name" @selected(request('sort') === 'name')>Name A–Z</option>
                        </select>
                    </form>
                </div>

                <p class="am-shop-results">{{ $products->total() }} product{{ $products->total() === 1 ? '' : 's' }}</p>

                @if($products->isEmpty())
                    <div class="am-empty">
                        <h3>No products found</h3>
                        <p>Try another category or search term.</p>
                        <a href="{{ route('shop.index') }}" class="am-btn am-btn--outline">View All Products</a>
                    </div>
                @else
                    <div class="am-product-grid am-product-grid--shop">
                        @foreach($products as $product)
                            @include('partials.am-product-card', ['product' => $product])
                        @endforeach
                    </div>
                    <div class="am-pagination">{{ $products->links('vendor.pagination.amerce') }}</div>
                @endif

                @if($showQuote && $categoryName)
                <div class="am-card am-shop-quote">
                    <div class="am-card__body">
                        <p class="am-card__label">Custom {{ $categoryName }}</p>
                        <h2 class="am-card__title">Need a custom size or finish?</h2>
                        <p class="am-card__text">Send dimensions and finish preference — we'll quote fabrication and Pan-India delivery.</p>
                        <x-lead-form-inline
                            :service-slug="request('category')"
                            :subject="$categoryName . ' — custom quote'"
                            type="service_inquiry" />
                    </div>
                </div>
                @endif

                @if($showCategoryCalc && $activeCategory)
                @include('partials.am-product-tabs', [
                    'title' => $activeCategory->name,
                    'descriptionHtml' => '<p>' . e($pageSubtitle) . '</p><p>Browse standard sizes below or use the sq ft calculator above for custom dimensions and instant estimates.</p>',
                    'careItems' => Service::careGuidelinesForCategory($activeCategory->slug),
                    'related' => null,
                ])
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
