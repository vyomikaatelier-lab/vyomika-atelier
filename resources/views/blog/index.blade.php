@extends('layouts.store')

@section('title', $pageSeo['title'] ?? 'Blog — Vyomika Atelier')

@section('content')

@include('partials.am-page-hero', [
    'label' => $index['label'] ?? 'Journal',
    'title' => $index['title'] ?? 'Ideas, Materials & Projects',
    'subtitle' => $index['subtitle'] ?? '',
])

<section class="am-page-body am-blog-index">
    <div class="am-container am-container--blog">

        @if($featured)
        <article class="am-blog-featured">
            <a href="{{ route('blog.show', $featured->slug) }}" class="am-blog-featured__link">
                <div class="am-blog-featured__media">
                    @if($featured->imageUrl())
                    @include('partials.am-blog-picture', [
                        'variant' => $featured->heroImageVariant(),
                        'alt' => $featured->heroAlt(),
                        'lazy' => false,
                    ])
                    @endif
                </div>
                <div class="am-blog-featured__body">
                    <span class="am-blog-featured__label">Featured</span>
                    @if($featured->categoryLabel())
                    <span class="am-blog-cat">{{ $featured->categoryLabel() }}</span>
                    @endif
                    <h2 class="am-blog-featured__title">{{ $featured->title }}</h2>
                    @if($featured->excerpt)
                    <p class="am-blog-featured__excerpt">{{ $featured->excerpt }}</p>
                    @endif
                    <div class="am-blog-meta">
                        <span>{{ $featured->author ?? 'Vyomika Atelier Editorial Team' }}</span>
                        <span>{{ $featured->published_at?->format('j M Y') }}</span>
                        <span>{{ $featured->readingTime() }} min read</span>
                    </div>
                    <span class="am-blog-featured__cta">Read article →</span>
                </div>
            </a>
        </article>
        @endif

        <form method="GET" action="{{ route('blog.index') }}" class="am-blog-search" role="search">
            @if($activeCategory !== '')
            <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif
            <label class="sr-only" for="blog-search">Search articles</label>
            <input id="blog-search" type="search" name="q" value="{{ $search ?? '' }}" placeholder="Search articles…" class="am-blog-search__input">
            <button type="submit" class="am-btn am-btn--outline am-blog-search__btn">Search</button>
            @if(($search ?? '') !== '')
            <a href="{{ route('blog.index', array_filter(['category' => $activeCategory ?: null])) }}" class="am-blog-search__clear">Clear</a>
            @endif
        </form>

        @if(count($categories))
        <nav class="am-blog-filters" aria-label="Filter articles by category">
            <a href="{{ route('blog.index', array_filter(['q' => $search ?: null])) }}"
               class="am-blog-filters__btn {{ $activeCategory === '' ? 'is-active' : '' }}">All</a>
            @foreach($categories as $cat)
            <a href="{{ route('blog.index', array_filter(['category' => $cat['slug'], 'q' => $search ?: null])) }}"
               class="am-blog-filters__btn {{ $activeCategory === $cat['slug'] ? 'is-active' : '' }}">{{ $cat['label'] }}</a>
            @endforeach
        </nav>
        @endif

        @if($posts->count())
        <div class="am-blog-grid">
            @foreach($posts as $post)
            <article class="am-blog-card">
                <a href="{{ route('blog.show', $post->slug) }}" class="am-blog-card__link">
                    @if($post->imageUrl())
                    <div class="am-blog-card__thumb">
                        @include('partials.am-blog-picture', [
                            'variant' => $post->heroImageVariant(),
                            'alt' => $post->heroAlt(),
                            'lazy' => true,
                        ])
                    </div>
                    @endif
                    <div class="am-blog-card__body">
                        <div class="am-blog-card__meta">
                            @if($post->categoryLabel())
                            <span class="am-blog-cat">{{ $post->categoryLabel() }}</span>
                            @endif
                            <span>{{ $post->published_at?->format('j M Y') }}</span>
                        </div>
                        <h3 class="am-blog-card__title">{{ $post->title }}</h3>
                        @if($post->excerpt)
                        <p class="am-blog-card__excerpt">{{ $post->excerpt }}</p>
                        @endif
                        <span class="am-blog-card__read">{{ $post->readingTime() }} min read</span>
                    </div>
                </a>
            </article>
            @endforeach
        </div>
        <div class="am-pagination">{{ $posts->appends(request()->query())->links('vendor.pagination.amerce') }}</div>
        @else
        <div class="am-blog-empty">
            @if(($search ?? '') !== '')
            <p>No articles match your search.</p>
            <p><a href="{{ route('blog.index') }}">View all articles</a></p>
            @elseif($activeCategory !== '')
            <p>No articles in this category yet.</p>
            <p><a href="{{ route('blog.index') }}">View all articles</a></p>
            @else
            <p>No articles published yet. Check back soon.</p>
            @endif
        </div>
        @endif

        @php
            $social = \App\Support\SiteContent::social();
            $whatsappSource = $social['whatsapp'] ?? config('site.brand.phone', '');
            $whatsappDigits = preg_replace('/\D/', '', (string) $whatsappSource);
            $whatsappUrl = $whatsappDigits !== '' ? 'https://wa.me/'.ltrim($whatsappDigits, '+') : null;
        @endphp
        <section class="am-blog-index-cta" aria-label="Studio contact options">
            <div class="am-blog-index-cta__inner">
                <button type="button" class="am-btn am-btn--primary" data-open-contact-studio>Discuss Your Project</button>
                <a href="{{ route('projects.index') }}" class="am-btn am-btn--outline">View Our Projects</a>
                @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" class="am-btn am-btn--outline" target="_blank" rel="noopener noreferrer">WhatsApp Studio</a>
                @endif
            </div>
        </section>
    </div>
</section>
@endsection
