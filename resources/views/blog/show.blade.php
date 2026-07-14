@extends('layouts.app')

@section('title', ($post->meta_title ?? $post->title) . ' — VYOMIKA ATELIER')

@push('styles')
@if($post->meta_description)
<meta name="description" content="{{ $post->meta_description }}">
@endif
@endpush

@section('content')
<article>
    <div class="relative h-[45vh] min-h-[280px] bg-brand-900">
        @if($post->image)
            <img src="{{ $post->image }}" alt="{{ $post->title }}" class="w-full h-full object-cover opacity-80">
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-brand-900/70 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 max-w-3xl mx-auto px-5 pb-10 text-center">
            <time class="va-label text-brand-200">{{ $post->published_at?->format('F d, Y') }}</time>
            <h1 class="font-serif text-4xl md:text-5xl text-white mt-3">{{ $post->title }}</h1>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-5 py-16">
        @if($post->excerpt)
            <p class="text-xl text-brand-600 leading-relaxed mb-10 font-light">{{ $post->excerpt }}</p>
        @endif
        <div class="prose-brand text-brand-700 leading-relaxed">{!! $post->content !!}</div>
        <div class="mt-12 pt-8 border-t border-brand-200 text-center">
            <a href="{{ route('blog.index') }}" class="va-btn-outline">← All Articles</a>
        </div>
    </div>
</article>
@endsection
