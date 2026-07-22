@extends('layouts.store')

@section('title', 'Vyomika Atelier — PVD Partitions & Metal Furniture')

@section('content')

@php
    use App\Support\SiteContent;
    use App\Support\StorefrontUrl;
    $heroSlides = SiteContent::heroSlides();
    $bestSellers = SiteContent::bestSellers();
    $categoryBanners = SiteContent::categoryBanners();
    $trending = SiteContent::trending();
    $spotlights = SiteContent::spotlights();
    $ctaBand = SiteContent::get('cta_band', []);
    $testimonials = SiteContent::testimonials();
    $blogSection = SiteContent::blogSection();
    $trustBadges = SiteContent::trustBadges();

    $bestSellerProducts = $shopItems->isNotEmpty() ? $shopItems->take(6) : collect($bestSellers['products'] ?? []);
    $trendingProducts = isset($trendingFromDb) && $trendingFromDb->isNotEmpty()
        ? $trendingFromDb->take(4)
        : collect($trending['products'] ?? []);
    $blogPosts = $blogItems->isNotEmpty() ? $blogItems->take(3) : collect($blogSection['posts'] ?? []);
@endphp

{{-- Hero carousel --}}
<section class="am-hero">
    <div class="am-hero__slides">
        @foreach($heroSlides as $i => $slide)
        <div class="am-hero__slide {{ $i === 0 ? 'is-active' : '' }}">
            <div class="am-hero__content">
                <p class="am-hero__kicker">{{ $slide['kicker'] ?? '' }}</p>
                <h1 class="am-hero__title">{{ $slide['title'] ?? '' }}</h1>
                <p class="am-hero__desc">{{ $slide['description'] ?? '' }}</p>
                <a href="{{ url($slide['cta_href'] ?? '/shop') }}" class="am-btn am-btn--primary am-btn--lg">{{ $slide['cta_label'] ?? 'Shop Now' }}</a>
            </div>
            <div class="am-hero__image">
                <img src="{{ $slide['image'] ?? '' }}" alt="{{ $slide['title'] ?? '' }}" @if($i === 0) fetchpriority="high" @else loading="lazy" @endif>
            </div>
        </div>
        @endforeach
    </div>
    <div class="am-hero__dots">
        @foreach($heroSlides as $i => $slide)
        <button type="button" class="am-hero__dot {{ $i === 0 ? 'is-active' : '' }}" aria-label="Slide {{ $i + 1 }}"></button>
        @endforeach
    </div>
</section>

{{-- Best sellers --}}
<section class="am-section am-section--white am-section--edge">
    <div class="am-section__intro">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2>{{ $bestSellers['title'] ?? 'Best-Selling Products' }}</h2>
                <p>{{ $bestSellers['subtitle'] ?? '' }}</p>
            </div>
            <a href="{{ StorefrontUrl::to('shop.index', [], '/shop') }}" class="am-section-head__link">{{ $bestSellers['cta_label'] ?? 'View All Products' }}</a>
        </div>
    </div>
    @php $banner = $bestSellers['banner'] ?? []; @endphp
    <div class="am-section__body">
        <div class="am-product-grid am-product-grid--with-banner">
            @if(!empty($banner))
            <a href="{{ url($banner['href'] ?? '/shop') }}" class="am-product-banner">
                <img src="{{ $banner['image'] ?? '' }}" alt="{{ $banner['title'] ?? '' }}" loading="lazy">
                <h3>{{ $banner['title'] ?? '' }}</h3>
                <p>{{ $banner['subtitle'] ?? '' }}</p>
                <span class="am-btn am-btn--white am-btn--sm">{{ $banner['cta'] ?? 'Shop now' }}</span>
            </a>
            @endif
            @foreach($bestSellerProducts as $product)
                @include('partials.am-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

{{-- Category banners --}}
<section class="am-section am-section--edge">
    <div class="am-section__body">
        <div class="am-cat-grid">
            @foreach($categoryBanners as $cat)
            <a href="{{ url($cat['href'] ?? '/shop') }}" class="am-cat-tile">
                <img src="{{ $cat['image'] ?? '' }}" alt="{{ $cat['title'] ?? '' }}" loading="lazy">
                <h3>{{ $cat['title'] ?? '' }}</h3>
                <p>{{ $cat['subtitle'] ?? '' }}</p>
                <span class="am-btn am-btn--white am-btn--sm">{{ $cat['cta'] ?? 'Shop Now' }}</span>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- Trending --}}
<section class="am-section am-section--white am-section--edge">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $trending['title'] ?? 'Trending Metal Finds' }}</h2>
            <p>{{ $trending['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-product-grid am-product-grid--4">
            @foreach($trendingProducts as $product)
                @include('partials.am-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

{{-- Spotlights --}}
<section class="am-section am-section--edge">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $spotlights['title'] ?? '' }}</h2>
            <p>{{ $spotlights['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-spotlight-grid">
            @foreach($spotlights['items'] ?? [] as $item)
            <div class="am-spotlight">
                <div class="am-spotlight__image">
                    <img src="{{ $item['image'] ?? '' }}" alt="{{ $item['title'] ?? '' }}" loading="lazy">
                </div>
                <div class="am-spotlight__body">
                    <h3>{{ $item['title'] ?? '' }}</h3>
                    <p>{{ $item['description'] ?? '' }}</p>
                    <p class="am-spotlight__price">{{ SiteContent::formatPrice($item['price'] ?? 0) }} <span style="font-weight:400;font-size:0.85rem;color:var(--am-muted)">{{ $item['price_unit'] ?? '' }}</span></p>
                    <a href="{{ url($item['href'] ?? '/shop') }}" class="am-btn am-btn--primary">{{ $item['cta'] ?? 'Buy now' }}</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA band --}}
<section class="am-cta-band">
    <h2>{{ $ctaBand['title'] ?? '' }}</h2>
    <p>{{ $ctaBand['description'] ?? '' }}</p>
    <a href="{{ url($ctaBand['cta_href'] ?? '/shop') }}" class="am-btn am-btn--primary am-btn--lg">{{ $ctaBand['cta_label'] ?? 'View All Products' }}</a>
</section>

{{-- Testimonials --}}
<section class="am-section am-testimonials">
    <div class="am-container">
        <div class="am-section-head">
            <h2>What Our Customers Say</h2>
            <p>Real stories from architects, designers, and homeowners across India.</p>
        </div>
        <div class="am-testimonial-slider">
            @foreach($testimonials as $i => $item)
            <div class="am-testimonial-slide {{ $i === 0 ? 'is-active' : '' }}">
                <p class="am-testimonial-quote">"{{ $item['quote'] }}"</p>
                <p class="am-testimonial-author">{{ $item['client'] }}</p>
                <p class="am-testimonial-role">{{ $item['role'] }}</p>
            </div>
            @endforeach
            <div class="am-testimonial-dots">
                @foreach($testimonials as $i => $item)
                <button type="button" class="am-testimonial-dot {{ $i === 0 ? 'is-active' : '' }}" aria-label="Testimonial {{ $i + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Blog --}}
<section class="am-section am-section--white am-section--edge">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $blogSection['title'] ?? 'Guides, Tips & Inspiration' }}</h2>
            <p>{{ $blogSection['subtitle'] ?? '' }}</p>
            <a href="{{ StorefrontUrl::to('blog.index', [], '/blog') }}" class="am-section-head__link">View all articles →</a>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-blog-grid">
            @foreach($blogPosts as $post)
            @php
                $isModel = $post instanceof \App\Models\BlogPost;
                $title = $isModel ? $post->title : ($post['title'] ?? '');
                $cat = $isModel ? 'Journal' : ($post['category'] ?? 'Blog');
                $date = $isModel ? ($post->published_at?->format('j F Y') ?? '') : ($post['date'] ?? '');
                $excerpt = $isModel ? ($post->excerpt ?? '') : ($post['excerpt'] ?? '');
                $image = $isModel ? $post->image : ($post['image'] ?? '');
                $slug = data_get($post, 'slug');
                $url = $slug
                    ? StorefrontUrl::to('blog.show', ['slug' => $slug], '/blog/'.$slug)
                    : StorefrontUrl::to('blog.index', [], '/blog');
            @endphp
            <article class="am-blog-card">
                <a href="{{ $url }}">
                    <div class="am-blog-card__thumb">
                        @if($image)<img src="{{ $image }}" alt="{{ $title }}" loading="lazy">@endif
                    </div>
                    <div class="am-blog-card__body">
                        <div class="am-blog-card__meta">
                            <span class="am-blog-cat">{{ $cat }}</span>
                            <span>{{ $date }}</span>
                        </div>
                        <h3 class="am-blog-card__title">{{ $title }}</h3>
                        @if($excerpt)<p class="am-blog-card__excerpt">{{ $excerpt }}</p>@endif
                    </div>
                </a>
            </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Trust badges --}}
<section class="am-trust">
    <div class="am-trust-grid">
            @foreach($trustBadges as $badge)
            <div class="am-trust-item">
                @if(($badge['icon'] ?? '') === 'shipping')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 6h13v10H1zM14 9h4l3 3v4h-7V9z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                @elseif(($badge['icon'] ?? '') === 'delivery')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @elseif(($badge['icon'] ?? '') === 'returns')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7v6h6M21 17a9 9 0 00-15-6.7L3 13"/></svg>
                @elseif(($badge['icon'] ?? '') === 'support')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2a7 7 0 00-7 7v3a3 3 0 003 3h1v-6H7a5 5 0 019.9-1M12 22v-4M8 22h8"/></svg>
                @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>
                @endif
                <h4>{{ $badge['title'] ?? '' }}</h4>
                <p>{{ $badge['text'] ?? '' }}</p>
            </div>
            @endforeach
    </div>
</section>

@endsection
