@extends('layouts.store')

@section('title', $pageSeo['title'] ?? $post->seoTitle())

@push('meta')
@if($post->published_at)
<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">
@endif
@if($post->lastUpdatedAt())
<meta property="article:modified_time" content="{{ $post->lastUpdatedAt()->toAtomString() }}">
@endif
@endpush

@push('jsonld')
<script type="application/ld+json">{!! json_encode(\App\Support\Seo\BlogSeo::articleSchema($post), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode(\App\Support\Seo\BlogSeo::breadcrumbSchema($post), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@php $faqLd = \App\Support\Seo\BlogSeo::faqSchema($post); @endphp
@if($faqLd)
<script type="application/ld+json">{!! json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
@endpush

@section('content')

@php
    $breadcrumbItems = [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Blog', 'url' => route('blog.index')],
    ];
    if ($post->categoryLabel()) {
        $breadcrumbItems[] = ['label' => $post->categoryLabel(), 'url' => route('blog.index', ['category' => $post->categorySlug()])];
    }
    $breadcrumbItems[] = ['label' => $post->title];
    $shareUrl = urlencode($post->canonicalUrl());
    $shareTitle = urlencode($post->title);
    $social = \App\Support\SiteContent::social();
    $whatsappSource = $social['whatsapp'] ?? config('site.brand.phone', '');
    $whatsappDigits = preg_replace('/\D/', '', (string) $whatsappSource);
    $whatsappShare = $whatsappDigits !== '' ? 'https://wa.me/'.ltrim($whatsappDigits, '+').'?text='.$shareTitle.'%20'.$shareUrl : null;
    $toc = $post->tableOfContents();
@endphp

<article class="am-blog-article" itemscope itemtype="https://schema.org/BlogPosting">
    <header class="am-blog-article__header am-container am-container--blog">
        @include('partials.am-breadcrumbs', ['items' => $breadcrumbItems, 'class' => 'am-breadcrumbs--compact'])
        <div class="article-masthead">
            <div class="article-masthead__content">
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
                    <span class="am-blog-meta__item" itemprop="author" itemscope itemtype="https://schema.org/Organization">
                        <svg class="am-blog-meta__icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span itemprop="name">{{ $post->author ?? 'Vyomika Atelier Editorial Team' }}</span>
                    </span>
                    @if($post->published_at)
                    <time class="am-blog-meta__item" datetime="{{ $post->published_at->toAtomString() }}" itemprop="datePublished">
                        <svg class="am-blog-meta__icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        {{ $post->published_at->format('j F Y') }}
                    </time>
                    @endif
                    @if($post->lastUpdatedAt())
                    <time class="am-blog-meta__item" datetime="{{ $post->lastUpdatedAt()->toAtomString() }}" itemprop="dateModified">
                        <svg class="am-blog-meta__icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/></svg>
                        Updated {{ $post->lastUpdatedAt()->format('j F Y') }}
                    </time>
                    @endif
                    <span class="am-blog-meta__item">
                        <svg class="am-blog-meta__icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        {{ $post->readingTime() }} min read
                    </span>
                </div>
            </div>
            @if($post->imageUrl())
            <figure class="article-masthead__media">
                @include('partials.am-blog-picture', [
                    'variant' => $post->heroImageVariant(),
                    'alt' => $post->heroAlt(),
                    'lazy' => false,
                    'attrs' => 'itemprop="image"',
                ])
                @if($post->hero_image_caption)
                <figcaption class="article-masthead__caption">{{ $post->hero_image_caption }}</figcaption>
                @endif
            </figure>
            @endif
        </div>
        <hr class="am-blog-article__divider" aria-hidden="true">
    </header>

    <div class="am-container am-container--blog am-blog-article__body">
        <div class="am-blog-article__layout">
            @if($post->showTableOfContents())
            <aside class="am-blog-article__sidebar" aria-label="Table of contents">
                <nav class="am-blog-toc" aria-labelledby="blog-toc-title">
                    <p id="blog-toc-title" class="am-blog-toc__title">In this article</p>
                    <ol class="am-blog-toc__list">
                        @foreach($toc as $index => $item)
                        <li><a href="#{{ $item['id'] }}" data-toc-link><span class="am-blog-toc__num">{{ $index + 1 }}.</span> {{ $item['text'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </aside>
            @endif

            <div class="am-blog-article__main">
                <div class="am-prose am-blog-article__content" itemprop="articleBody">
                    {!! $post->content !!}
                </div>

                @include('partials.blog-share', [
                    'shareUrl' => $post->canonicalUrl(),
                    'shareTitle' => $post->title,
                    'whatsappShare' => $whatsappShare,
                ])

                @if($post->hasGallery())
                <section class="am-blog-block" aria-labelledby="blog-gallery-title">
                    <h2 id="blog-gallery-title" class="am-blog-block__title">Project Gallery</h2>
                    <div class="am-blog-gallery">
                        @foreach($post->galleryItems() as $item)
                        <figure class="am-blog-gallery__item">
                            <img src="{{ $item['url'] }}" alt="{{ $item['alt'] }}" width="640" height="480" loading="lazy" decoding="async">
                            @if($item['caption'])
                            <figcaption>{{ $item['caption'] }}</figcaption>
                            @endif
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
                            <a href="{{ route('shop.show', $product->slug) }}" class="am-blog-related-card__link">
                                @if($product->image)
                                <div class="am-blog-related-card__thumb">
                                    <img src="{{ $product->image }}" alt="{{ $product->name }} — Vyomika Atelier" loading="lazy">
                                </div>
                                @endif
                                <h3 class="am-blog-related-card__title">{{ $product->name }}</h3>
                                <span class="am-blog-related-card__cta">View product →</span>
                            </a>
                        </article>
                        @endforeach
                    </div>
                    <p class="am-blog-block__more"><a href="{{ \App\Support\StorefrontNavigation::primaryPublishedShopUrl() }}">Browse collections</a></p>
                </section>
                @endif

                @if($relatedServices->isNotEmpty())
                <section class="am-blog-block" aria-labelledby="blog-services-title">
                    <h2 id="blog-services-title" class="am-blog-block__title">Related Studio Collections</h2>
                    <div class="am-blog-related-grid">
                        @foreach($relatedServices as $service)
                        <article class="am-blog-related-card">
                            <a href="{{ \App\Support\StorefrontNavigation::publicServicesRedirectUrl($service->slug) }}" class="am-blog-related-card__link">
                                @if($service->image)
                                <div class="am-blog-related-card__thumb">
                                    <img src="{{ $service->image }}" alt="{{ $service->name }} — Vyomika Atelier" loading="lazy">
                                </div>
                                @endif
                                <h3 class="am-blog-related-card__title">{{ $service->name }}</h3>
                                <span class="am-blog-related-card__cta">View collection →</span>
                            </a>
                        </article>
                        @endforeach
                    </div>
                </section>
                @endif

                @if($relatedProjects->isNotEmpty())
                <section class="am-blog-block" aria-labelledby="blog-projects-title">
                    <h2 id="blog-projects-title" class="am-blog-block__title">Related Projects</h2>
                    <div class="am-blog-related-grid">
                        @foreach($relatedProjects as $project)
                        <article class="am-blog-related-card">
                            <a href="{{ route('projects.index') }}" class="am-blog-related-card__link">
                                @if($project->imageUrl())
                                <div class="am-blog-related-card__thumb">
                                    <img src="{{ $project->imageUrl() }}" alt="{{ $project->displayAlt() }}" loading="lazy">
                                </div>
                                @endif
                                <h3 class="am-blog-related-card__title">{{ $project->project_name }}</h3>
                                @if($project->description)
                                <p class="am-blog-related-card__text">{{ \Illuminate\Support\Str::limit($project->description, 120) }}</p>
                                @endif
                                <span class="am-blog-related-card__cta">View projects →</span>
                            </a>
                        </article>
                        @endforeach
                    </div>
                    <p class="am-blog-block__more"><a href="{{ route('projects.index') }}">See all projects</a></p>
                </section>
                @endif

                @if(count($post->validFaqItems()))
                <section class="am-blog-block am-blog-faq" aria-labelledby="blog-faq-title">
                    <h2 id="blog-faq-title" class="am-blog-block__title">Frequently Asked Questions</h2>
                    <div class="am-corten-faq-wrap">
                        <div class="am-corten-faq am-corten-faq--light">
                            @foreach($post->validFaqItems() as $item)
                            <details class="am-corten-faq__item">
                                <summary>{{ $item['question'] }}</summary>
                                <p>{{ $item['answer'] }}</p>
                            </details>
                            @endforeach
                        </div>
                    </div>
                </section>
                @endif

                @if($relatedArticles->isNotEmpty())
                <section class="am-blog-block" aria-labelledby="blog-related-title">
                    <h2 id="blog-related-title" class="am-blog-block__title">Related Articles</h2>
                    <div class="am-blog-grid am-blog-grid--related">
                        @foreach($relatedArticles as $article)
                        <article class="am-blog-card">
                            <a href="{{ route('blog.show', $article->slug) }}" class="am-blog-card__link">
                                @if($article->imageUrl())
                                <div class="am-blog-card__thumb">
                                    @include('partials.am-blog-picture', [
                                        'variant' => $article->heroImageVariant(),
                                        'alt' => $article->heroAlt(),
                                        'lazy' => true,
                                    ])
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

                @if(!empty($adjacent['prev']) || !empty($adjacent['next']))
                <nav class="am-blog-adjacent" aria-label="Article navigation">
                    @if(!empty($adjacent['prev']))
                    <a href="{{ route('blog.show', $adjacent['prev']->slug) }}" class="am-blog-adjacent__link am-blog-adjacent__link--prev">
                        <span class="am-blog-adjacent__label">Previous</span>
                        <span class="am-blog-adjacent__title">{{ $adjacent['prev']->title }}</span>
                    </a>
                    @endif
                    @if(!empty($adjacent['next']))
                    <a href="{{ route('blog.show', $adjacent['next']->slug) }}" class="am-blog-adjacent__link am-blog-adjacent__link--next">
                        <span class="am-blog-adjacent__label">Next</span>
                        <span class="am-blog-adjacent__title">{{ $adjacent['next']->title }}</span>
                    </a>
                    @endif
                </nav>
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
            </div>
        </div>
    </div>
</article>
@endsection

@push('scripts')
<script>
(function () {
    var prose = document.querySelector('.am-blog-article__content');
    if (!prose) return;
    var headings = prose.querySelectorAll('h2');
    headings.forEach(function (heading, index) {
        if (!heading.id) heading.id = 'section-' + (index + 1);
    });

    var tocLinks = document.querySelectorAll('[data-toc-link]');
    if (!tocLinks.length || !headings.length) return;

    function setActive(id) {
        tocLinks.forEach(function (link) {
            link.classList.toggle('is-active', link.getAttribute('href') === '#' + id);
        });
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) setActive(entry.target.id);
            });
        }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });
        headings.forEach(function (heading) { observer.observe(heading); });
    } else if (headings[0]) {
        setActive(headings[0].id);
    }
})();
</script>
@endpush
