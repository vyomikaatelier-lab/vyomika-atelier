@extends('layouts.admin')
@section('title', 'Edit '.$slug)
@section('content')
<div class="mb-4"><a href="{{ route('admin.collection-pages.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-2">Collection: {{ $slug }}</h1>
@if(request('saved') || session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') ?: 'Page saved successfully.' }}</div>
@endif
@if(data_get($stored, 'hero.title'))
<p class="text-xs text-gray-500 mb-4">Database hero title: <strong>{{ data_get($stored, 'hero.title') }}</strong></p>
@endif
<form method="POST" action="{{ route('admin.collection-pages.update', $slug) }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @method('PUT')
    <input type="hidden" name="_page_save" value="1">
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $page['meta_title'] ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Gallery title</label><input name="gallery_title" value="{{ old('gallery_title', $page['gallery_title'] ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page['meta_description'] ?? '') }}</textarea></div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Cover photo (desktop, tablet &amp; mobile)</p>
        <p class="text-xs text-gray-500">{{ \App\Support\ResponsiveHero::adminUploadIntro() }}</p>
        <input name="hero_title" value="{{ old('hero_title', data_get($page, 'hero.title')) }}" placeholder="Hero title" class="w-full border px-3 py-2 rounded">
        <textarea name="hero_subtitle" rows="2" placeholder="Hero subtitle" class="w-full border px-3 py-2 rounded">{{ old('hero_subtitle', data_get($page, 'hero.subtitle')) }}</textarea>
        @include('admin.partials.responsive-hero-images', ['prefix' => 'hero', 'hero' => data_get($page, 'hero', [])])
    </div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Intro</p>
        <input name="intro_title" value="{{ old('intro_title', data_get($page, 'intro.title')) }}" placeholder="Intro title" class="w-full border px-3 py-2 rounded">
        <textarea name="intro_body" rows="4" placeholder="Intro body" class="w-full border px-3 py-2 rounded">{{ old('intro_body', data_get($page, 'intro.body')) }}</textarea>
    </div>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save page</button>
</form>
@endsection
