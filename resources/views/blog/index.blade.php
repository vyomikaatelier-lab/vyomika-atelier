@extends('layouts.store')

@section('title', \App\Support\BlogContent::metaTitle())

@push('meta')
<meta name="description" content="{{ \App\Support\BlogContent::metaDescription() }}">
<link rel="canonical" href="{{ route('blog.index') }}">
@endpush

@section('content')

@include('partials.am-page-hero', [
    'label' => $index['label'] ?? 'Journal',
    'title' => $index['title'] ?? 'Ideas, Materials & Projects',
    'subtitle' => $index['subtitle'] ?? '',
])

<section class="am-page-body am-blog-index">
    <div class="am-container">

        @if($featured)
        <article class="am-blog-featured">
            <a href="{{ route('blog.show', $featured->slug) }}" class="am-blog-featured__link">
                <div class="am-blog-featured__media">
                    @if($featured->image)
                    <img src="{{ $featured->image }}" alt="{{ $featured->heroAlt() }}" loading="eager">
                    @endif
                </div>
                <div class="am-blog-featured__body">
                    <span class="am-blog-featured__label">Featured</span>
                    @if($featured->categoryLabel())
                    <span class="am-blog-cat">{{ $featured->categoryLabel() }}</span>
                    @endif
                    <h2 class="am-blog-featured__title">{{ $featured->title }}</h2>
                    <p class="am-blog-featured__excerpt">{{ $featured->excerpt }}</p>
                    <div class="am-blog-meta">
                        <span>{{ $featured->author ?? 'Vyomika Atelier' }}</span>
                        <span>{{ $featured->published_at?->format('j M Y') }}</span>
                        <span>{{ $featured->readingTime() }} min read</span>
                    </div>
                    <span class="am-blog-featured__cta">Read article →</span>
                </div>
            </a>
        </article>
        @endif

        @if(count($categories))
        <nav class="am-blog-filters" aria-label="Filter articles by category">
            <a href="{{ route('blog.index') }}"
               class="am-blog-filters__btn {{ $activeCategory === '' ? 'is-active' : '' }}">All</a>
            @foreach($categories as $cat)
            <a href="{{ route('blog.index', ['category' => $cat['slug']]) }}"
               class="am-blog-filters__btn {{ $activeCategory === $cat['slug'] ? 'is-active' : '' }}">{{ $cat['label'] }}</a>
            @endforeach
        </nav>
        @endif

        @if($posts->count())
        <div class="am-blog-grid">
            @foreach($posts as $post)
            <article class="am-blog-card">
                <a href="{{ route('blog.show', $post->slug) }}">
                    @if($post->image)
                    <div class="am-blog-card__thumb">
                        <img src="{{ $post->image }}" alt="{{ $post->heroAlt() }}" loading="lazy">
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
                        <p class="am-blog-card__excerpt">{{ $post->excerpt }}</p>
                        <span class="am-blog-card__read">{{ $post->readingTime() }} min read</span>
                    </div>
                </a>
            </article>
            @endforeach
        </div>
        <div class="am-pagination">{{ $posts->links('vendor.pagination.amerce') }}</div>
        @else
        <p class="am-blog-empty">No articles in this category yet. <a href="{{ route('blog.index') }}">View all articles</a>.</p>
        @endif
    </div>
</section>
@endsection
