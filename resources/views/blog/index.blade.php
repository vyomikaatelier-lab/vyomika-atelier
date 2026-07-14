@extends('layouts.app')

@section('title', 'Blog — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Insights</p>
    <h1 class="font-serif text-5xl text-brand-900">Blog</h1>
    <p class="text-brand-500 mt-4 max-w-lg mx-auto">Design inspiration, material guides, and project insights from the VYOMIKA ATELIER team.</p>
</div>

<div class="max-w-7xl mx-auto px-5 py-16">
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-10">
        @foreach($posts as $post)
        <article>
            <a href="{{ route('blog.show', $post->slug) }}" class="va-card group block">
                @if($post->image)
                    <div class="aspect-[16/10] bg-brand-100 overflow-hidden mb-5">
                        <img src="{{ $post->image }}" alt="{{ $post->title }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <time class="text-[10px] uppercase tracking-[0.2em] text-brand-400">{{ $post->published_at?->format('M d, Y') }}</time>
                <h2 class="font-serif text-2xl text-brand-900 mt-2 group-hover:text-brand-500 transition">{{ $post->title }}</h2>
                <p class="text-sm text-brand-500 mt-3 leading-relaxed">{{ $post->excerpt }}</p>
                <span class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mt-4 inline-block">Read more →</span>
            </a>
        </article>
        @endforeach
    </div>
    <div class="mt-14">{{ $posts->links() }}</div>
</div>
@endsection
