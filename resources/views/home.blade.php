@extends('layouts.store')

@section('title', $pageSeo['title'] ?? 'Vyomika Atelier | PVD Partitions & Architectural Metalwork India')

@section('content')

@php
    use App\Support\SiteContent;
    use App\Support\StorefrontNavigation;
    use App\Support\StorefrontUrl;
    $heroSlides = SiteContent::heroSlides();
    $bestSellers = SiteContent::bestSellers();
    $homepageCategoryTiles = $homepageCategoryTiles ?? StorefrontNavigation::homepageCategoryTiles();
    $collectionSection = SiteContent::get('homepage.collections', []);
    $studioSpotlights = $studioSpotlights ?? StorefrontNavigation::homepageStudioSpotlights();
    $homepageUsps = SiteContent::homepageUsps();
    $trending = SiteContent::trending();
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
                @php $heroCta = StorefrontNavigation::resolveCta($slide['cta_href'] ?? null, $slide['cta_label'] ?? null); @endphp
                <a href="{{ url($heroCta['href']) }}" class="am-btn am-btn--primary am-btn--lg">{{ $heroCta['label'] !== '' ? $heroCta['label'] : 'Explore' }}</a>
            </div>
            <div class="am-hero__image">
                @include('partials.am-hero-picture', ['slide' => $slide, 'priority' => $i === 0])
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

{{-- Signature finishes ribbon --}}
<section class="am-finish-strip am-reveal" aria-label="Signature PVD finishes">
    <div class="am-finish-strip__inner">
        <p class="am-finish-strip__eyebrow">The Atelier Palette</p>
        <ul class="am-finish-strip__swatches">
            <li>
                <span class="am-finish-swatch am-finish-swatch--champagne" aria-hidden="true"></span>
                <span>Champagne</span>
            </li>
            <li>
                <span class="am-finish-swatch am-finish-swatch--rose" aria-hidden="true"></span>
                <span>Rose Gold</span>
            </li>
            <li>
                <span class="am-finish-swatch am-finish-swatch--black" aria-hidden="true"></span>
                <span>Matte Black</span>
            </li>
        </ul>
        <p class="am-finish-strip__note">Grade 304/316 stainless · Fabricated in Delhi · Delivered across India</p>
    </div>
</section>

{{-- Collection row --}}
@if(SiteContent::homepageSectionEnabled('category_banners') && $homepageCategoryTiles !== [])
<section class="am-section am-section--edge am-reveal am-reveal--delay">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $collectionSection['title'] ?? 'Explore Our Collections' }}</h2>
            <p>{{ $collectionSection['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-cat-scroll-wrap" data-cat-carousel>
            <div class="am-cat-scroll" tabindex="0" role="region" aria-label="Shop and studio collections">
                @foreach($homepageCategoryTiles as $i => $cat)
                <a href="{{ url($cat['href']) }}" class="am-cat-tile am-reveal" style="transition-delay: {{ min($i, 4) * 0.08 }}s">
                    <span class="am-cat-tile__index" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    @if(!empty($cat['image']))
                    <img src="{{ $cat['image'] }}" alt="{{ $cat['title'] ?? '' }}" loading="lazy">
                    @endif
                    <h3>{{ $cat['title'] ?? '' }}</h3>
                    @if(!empty($cat['subtitle']))
                    <p>{{ $cat['subtitle'] }}</p>
                    @endif
                    <span class="am-btn am-btn--white am-btn--sm">{{ $cat['cta'] ?? 'View collection' }}</span>
                </a>
                @endforeach
                @foreach($homepageCategoryTiles as $i => $cat)
                <a href="{{ url($cat['href']) }}" class="am-cat-tile am-cat-tile--clone" tabindex="-1" aria-hidden="true">
                    @if(!empty($cat['image']))
                    <img src="{{ $cat['image'] }}" alt="" loading="lazy">
                    @endif
                    <h3>{{ $cat['title'] ?? '' }}</h3>
                    @if(!empty($cat['subtitle']))
                    <p>{{ $cat['subtitle'] }}</p>
                    @endif
                    <span class="am-btn am-btn--white am-btn--sm">{{ $cat['cta'] ?? 'View collection' }}</span>
                </a>
                @endforeach
            </div>
            <div class="am-cat-scroll__hint" aria-hidden="true">Swipe to explore</div>
            <div class="am-cat-scroll__dots" role="tablist" aria-label="Collection slides">
                @foreach($homepageCategoryTiles as $i => $cat)
                <button type="button" class="am-cat-scroll__dot {{ $i === 0 ? 'is-active' : '' }}" role="tab" aria-label="{{ $cat['title'] ?? 'Collection '.($i + 1) }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- Studio capabilities: partitions + calculator, railings, corten --}}
@if(SiteContent::homepageSectionEnabled('studio_spotlights') && !empty($studioSpotlights['items']))
<section class="am-section am-section--white am-section--edge am-reveal">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $studioSpotlights['title'] ?? 'Bespoke Studio Capabilities' }}</h2>
            <p>{{ $studioSpotlights['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-studio-spotlights am-studio-spotlights--portrait">
            @foreach($studioSpotlights['items'] as $spotlight)
            <article class="am-studio-spotlight am-studio-spotlight--portrait {{ !empty($spotlight['has_calculator']) ? 'am-studio-spotlight--calc' : '' }}{{ !empty($spotlight['has_form']) ? ' am-studio-spotlight--form' : '' }}">
                <a href="{{ url($spotlight['href'] ?? '#') }}" class="am-studio-spotlight__media">
                    @if(!empty($spotlight['image']))
                    <img src="{{ $spotlight['image'] }}" alt="{{ $spotlight['title'] ?? '' }}" loading="lazy">
                    @endif
                    @if(!empty($spotlight['badge']))
                    <span class="am-studio-spotlight__badge">{{ $spotlight['badge'] }}</span>
                    @endif
                </a>
                <div class="am-studio-spotlight__body">
                    <h3><a href="{{ url($spotlight['href'] ?? '#') }}">{{ $spotlight['title'] ?? '' }}</a></h3>
                    <p>{{ $spotlight['subtitle'] ?? '' }}</p>
                    @if(!empty($spotlight['has_calculator']))
                    <div class="am-studio-spotlight__calc">
                        @include('partials.am-calculator', [
                            'rate' => $spotlight['rate'] ?? 1800,
                            'serviceSlug' => 'partitions',
                            'serviceName' => 'PVD Partitions',
                            'calcTitle' => 'Estimate your partition',
                            'hideOrderButton' => true,
                        ])
                        @include('partials.am-studio-spotlight-actions', [
                            'primaryType' => 'order',
                            'primaryLabel' => 'Order Now',
                            'orderServiceSlug' => 'partitions',
                            'orderServiceName' => 'PVD Partitions',
                            'exploreHref' => $spotlight['href'] ?? '#',
                            'exploreLabel' => $spotlight['cta'] ?? 'Learn more',
                        ])
                    </div>
                    @elseif(!empty($spotlight['has_form']) && !empty($spotlight['form']))
                    @php
                        $spotlightFormId = 'studio-spotlight-' . preg_replace('/[^a-z0-9-]+/i', '-', $spotlight['form']['service_slug'] ?? 'enquiry');
                    @endphp
                    <div class="am-studio-spotlight__form">
                        @include('partials.am-studio-spotlight-form', [
                            'title' => $spotlight['form']['title'] ?? 'Quick quote',
                            'type' => $spotlight['form']['type'] ?? 'service_inquiry',
                            'serviceSlug' => $spotlight['form']['service_slug'] ?? '',
                            'subject' => $spotlight['form']['subject'] ?? '',
                            'submitLabel' => $spotlight['form']['submit_label'] ?? 'Send enquiry',
                            'messagePlaceholder' => $spotlight['form']['message_placeholder'] ?? 'Brief project details — location, dimensions, timeline…',
                            'formKey' => $spotlight['form']['form_key'] ?? 'service_inquiry',
                        ])
                        @include('partials.am-studio-spotlight-actions', [
                            'primaryType' => 'submit',
                            'primaryLabel' => $spotlight['form']['submit_label'] ?? 'Send enquiry',
                            'formId' => $spotlightFormId . '-form',
                            'exploreHref' => $spotlight['href'] ?? '#',
                            'exploreLabel' => $spotlight['cta'] ?? 'Learn more',
                        ])
                    </div>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Best sellers (product grid only — no side banner) --}}
@if(SiteContent::homepageSectionEnabled('best_sellers'))
<section class="am-section am-section--white am-section--edge am-reveal">
    <div class="am-section__intro">
        <div class="am-section-head am-section-head--row">
            <div>
                <h2>{{ $bestSellers['title'] ?? 'Best-Selling Products' }}</h2>
                <p>{{ $bestSellers['subtitle'] ?? '' }}</p>
            </div>
            <a href="{{ StorefrontNavigation::resolveHref(\App\Support\StorefrontRoutes::primaryShopUrl()) }}" class="am-section-head__link">{{ StorefrontNavigation::resolveCta(\App\Support\StorefrontRoutes::primaryShopUrl(), $bestSellers['cta_label'] ?? null)['label'] }}</a>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-product-grid am-product-grid--6 am-product-grid--portrait">
            @foreach($bestSellerProducts as $product)
                @include('partials.am-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Trending --}}
@if(SiteContent::homepageSectionEnabled('trending'))
<section class="am-section am-section--edge am-reveal am-reveal--delay">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $trending['title'] ?? 'Trending Metal Finds' }}</h2>
            <p>{{ $trending['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-product-grid am-product-grid--4 am-product-grid--portrait">
            @foreach($trendingProducts as $product)
                @include('partials.am-product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- USP highlights --}}
@if(SiteContent::homepageSectionEnabled('usps') && !empty($homepageUsps['items']))
<section class="am-section am-section--cream am-section--edge am-reveal">
    <div class="am-section__intro">
        <div class="am-section-head">
            <h2>{{ $homepageUsps['title'] ?? 'The Vyomika Difference' }}</h2>
            <p>{{ $homepageUsps['subtitle'] ?? '' }}</p>
        </div>
    </div>
    <div class="am-section__body">
        <div class="am-usp-grid">
            @foreach($homepageUsps['items'] as $usp)
            <div class="am-usp-item">
                <div class="am-usp-item__icon">
                    @include('partials.am-usp-icon', ['icon' => $usp['icon'] ?? 'quality'])
                </div>
                <h3>{{ $usp['title'] ?? '' }}</h3>
                <p>{{ $usp['text'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- CTA band --}}
@if(SiteContent::homepageSectionEnabled('cta_band'))
<section class="am-cta-band">
    <h2>{{ $ctaBand['title'] ?? '' }}</h2>
    <p>{{ $ctaBand['description'] ?? '' }}</p>
    @php $ctaBandResolved = StorefrontNavigation::resolveCta($ctaBand['cta_href'] ?? null, $ctaBand['cta_label'] ?? null); @endphp
    <a href="{{ url($ctaBandResolved['href']) }}" class="am-btn am-btn--primary am-btn--lg">{{ $ctaBandResolved['label'] !== '' ? $ctaBandResolved['label'] : ('Shop '.StorefrontNavigation::primaryPublishedShopLabel()) }}</a>
</section>
@endif

{{-- Testimonials --}}
@if(SiteContent::homepageSectionEnabled('testimonials'))
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
@endif

{{-- Blog --}}
@if(SiteContent::homepageSectionEnabled('blog'))
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
@endif

{{-- Trust badges --}}
@if(SiteContent::homepageSectionEnabled('trust_badges'))
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
@endif

@endsection
