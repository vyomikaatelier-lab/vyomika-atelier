@extends('layouts.store')

@section('title', $post->seoTitle())

@push('meta')
<meta name="description" content="{{ $post->seoDescription() }}">
<link rel="canonical" href="{{ $post->canonicalUrl() }}">
<meta property="og:title" content="{{ $post->seoTitle() }}">
<meta property="og:description" content="{{ $post->seoDescription() }}">
<meta property="og:type" content="article">
<meta property="og:url" content="{{ $post->canonicalUrl() }}">
@if($post->image)
<meta property="og:image" content="{{ $post->image }}">
@endif
@if($post->published_at)
<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">
@endif
@endpush

@push('styles')
@php
    $articleLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->seoDescription(),
        'author' => [
            '@type' => 'Organization',
            'name' => $post->author ?? 'Vyomika Atelier',
            'url' => url('/'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Vyomika Atelier',
            'url' => url('/'),
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $post->canonicalUrl(),
        ],
        'datePublished' => $post->published_at?->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString() ?? $post->published_at?->toAtomString(),
    ];
    if ($post->image) {
        $articleLd['image'] = [$post->image];
    }

    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('blog.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $post->canonicalUrl()],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($articleLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

<div class="am-container" style="padding-top:1.5rem">
@include('partials.am-breadcrumbs', [
    'items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Blog', 'url' => route('blog.index')],
        ['label' => $post->title],
    ],
])
</div>

<article class="am-blog-article" itemscope itemtype="https://schema.org/Article">
    <header class="am-blog-article__header am-container">
        @if($post->categoryLabel())
        <p class="am-blog-article__category">
            <a href="{{ route('blog.index', ['category' => $post->categorySlug()]) }}">{{ $post->categoryLabel() }}</a>
        </p>
        @endif
        <h1 class="am-blog-article__title" itemprop="headline">{{ $post->title }}</h1>
        @if($post->excerpt)
        <p class="am-blog-article__excerpt" itemprop="description">{{ $post->excerpt }}</p>
        @endif
        <div class="am-blog-meta am-blog-article__meta">
            <span itemprop="author" itemscope itemtype="https://schema.org/Organization">
                <span itemprop="name">{{ $post->author ?? 'Vyomika Atelier' }}</span>
            </span>
            @if($post->published_at)
            <time datetime="{{ $post->published_at->toAtomString() }}" itemprop="datePublished">{{ $post->published_at->format('j F Y') }}</time>
            @endif
            <span>{{ $post->readingTime() }} min read</span>
        </div>
    </header>

    @if($post->image)
    <figure class="am-blog-article__hero">
        <img src="{{ $post->image }}" alt="{{ $post->heroAlt() }}" itemprop="image" loading="eager">
    </figure>
    @endif

    <div class="am-container am-blog-article__body">
        <div class="am-prose am-blog-article__content" itemprop="articleBody">
            {!! $post->content !!}
        </div>

        @if($post->hasGallery())
        <section class="am-blog-block" aria-labelledby="blog-gallery-title">
            <h2 id="blog-gallery-title" class="am-blog-block__title">Project Gallery</h2>
            <div class="am-blog-gallery">
                @foreach($post->gallery as $image)
                <figure class="am-blog-gallery__item">
                    <img src="{{ $image }}" alt="{{ $post->title }} — fabrication detail" loading="lazy">
                </figure>
                @endforeach
            </div>
        </section>
        @endif

        @if($relatedProducts->isNotEmpty())
        <section class="am-blog-block" aria-labelledby="blog-products-title">
            <h2 id="blog-products-title" class="am-blog-block__title">Related Products</h2>
            <div class="am-blog-related-grid">
                @foreach($relatedProducts as $product)
                <article class="am-blog-related-card">
                    <a href="{{ route('shop.show', $product->slug) }}">
                        @if($product->image)
                        <div class="am-blog-related-card__thumb">
                            <img src="{{ $product->image }}" alt="{{ $product->name }} — Vyomika Atelier" loading="lazy">
                        </div>
                        @endif
                        <h3 class="am-blog-related-card__title">{{ $product->name }}</h3>
                        <span class="am-blog-related-card__link">View product →</span>
                    </a>
                </article>
                @endforeach
            </div>
            <p class="am-blog-block__more"><a href="{{ route('shop.index') }}">Browse all products</a></p>
        </section>
        @endif

        @if($relatedProjects->isNotEmpty())
        <section class="am-blog-block" aria-labelledby="blog-projects-title">
            <h2 id="blog-projects-title" class="am-blog-block__title">Related Projects</h2>
            <div class="am-blog-related-grid">
                @foreach($relatedProjects as $project)
                <article class="am-blog-related-card">
                    <a href="{{ route('projects.show', $project->slug) }}">
                        @if($project->image)
                        <div class="am-blog-related-card__thumb">
                            <img src="{{ $project->image }}" alt="{{ $project->title }} — Vyomika Atelier project" loading="lazy">
                        </div>
                        @endif
                        <h3 class="am-blog-related-card__title">{{ $project->title }}</h3>
                        @if($project->summary)
                        <p class="am-blog-related-card__text">{{ $project->summary }}</p>
                        @endif
                        <span class="am-blog-related-card__link">View project →</span>
                    </a>
                </article>
                @endforeach
            </div>
            <p class="am-blog-block__more"><a href="{{ route('projects.index') }}">See all projects</a></p>
        </section>
        @endif

        @if(count($post->faqItems()))
        <section class="am-blog-block am-blog-faq" aria-labelledby="blog-faq-title">
            <h2 id="blog-faq-title" class="am-blog-block__title">Frequently Asked Questions</h2>
            <div class="am-corten-faq-wrap">
                <div class="am-corten-faq am-corten-faq--light">
                    @foreach($post->faqItems() as $item)
                    <details class="am-corten-faq__item">
                        <summary>{{ $item['question'] ?? '' }}</summary>
                        <p>{{ $item['answer'] ?? '' }}</p>
                    </details>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <section class="am-blog-cta" aria-labelledby="blog-cta-title">
            <div class="am-blog-cta__inner">
                <h2 id="blog-cta-title" class="am-blog-cta__title">Discuss Your Project</h2>
                <p class="am-blog-cta__text">Share drawings, dimensions, and finish preferences — our Delhi studio team responds within one business day.</p>
                <div class="am-blog-cta__actions">
                    <button type="button" class="am-btn am-btn--primary" data-open-contact-studio data-contact-context="Re: {{ $post->title }}">Contact Studio</button>
                    <a href="{{ route('professionals.index') }}" class="am-btn am-btn--outline">Trade Programme</a>
                </div>
                <p class="am-blog-cta__contact">
                    <a href="mailto:namaste@vyomikaatelier.com">namaste@vyomikaatelier.com</a>
                    · <a href="tel:+919205850254">+91 9205850254</a>
                </p>
            </div>
        </section>

        @if($relatedArticles->isNotEmpty())
        <section class="am-blog-block" aria-labelledby="blog-related-title">
            <h2 id="blog-related-title" class="am-blog-block__title">Related Articles</h2>
            <div class="am-blog-grid am-blog-grid--related">
                @foreach($relatedArticles as $article)
                <article class="am-blog-card">
                    <a href="{{ route('blog.show', $article->slug) }}">
                        @if($article->image)
                        <div class="am-blog-card__thumb">
                            <img src="{{ $article->image }}" alt="{{ $article->heroAlt() }}" loading="lazy">
                        </div>
                        @endif
                        <div class="am-blog-card__body">
                            <div class="am-blog-card__meta">
                                @if($article->categoryLabel())
                                <span class="am-blog-cat">{{ $article->categoryLabel() }}</span>
                                @endif
                                <span>{{ $article->readingTime() }} min read</span>
                            </div>
                            <h3 class="am-blog-card__title">{{ $article->title }}</h3>
                        </div>
                    </a>
                </article>
                @endforeach
            </div>
            <p class="am-blog-block__more"><a href="{{ route('blog.index') }}">← All articles</a></p>
        </section>
        @endif
    </div>
</article>
@endsection
