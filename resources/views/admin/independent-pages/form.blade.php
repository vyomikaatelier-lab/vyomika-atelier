@extends('layouts.admin')
@section('title', 'Edit '.$label)
@section('content')
<div class="mb-4"><a href="{{ route('admin.independent-pages.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $label }}</h1>
<form method="POST" action="{{ route('admin.independent-pages.update', $slug) }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @method('PUT')
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $page['meta_title'] ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Slug</label><input value="{{ $slug }}" disabled class="w-full border px-3 py-2 rounded bg-gray-50 text-gray-500"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page['meta_description'] ?? '') }}</textarea></div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Hero</p>
        @php $heroImage = data_get($page, 'hero.image'); @endphp
        @if($heroImage)
            <img src="{{ \App\Support\MediaUrl::resolve($heroImage) ?? $heroImage }}" alt="" class="w-full max-w-md h-40 object-cover rounded border">
        @endif
        <input name="hero_title" value="{{ old('hero_title', data_get($page, 'hero.title')) }}" placeholder="Hero title" class="w-full border px-3 py-2 rounded">
        <textarea name="hero_subtitle" rows="2" placeholder="Hero subtitle" class="w-full border px-3 py-2 rounded">{{ old('hero_subtitle', data_get($page, 'hero.subtitle')) }}</textarea>
        <input name="hero_image" value="{{ old('hero_image', $heroImage) }}" placeholder="Hero image URL" class="w-full border px-3 py-2 rounded">
        <input type="file" name="hero_image_file" accept="image/*">
    </div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Intro</p>
        <input name="intro_title" value="{{ old('intro_title', data_get($page, 'intro.title')) }}" placeholder="Intro title" class="w-full border px-3 py-2 rounded">
        <textarea name="intro_body" rows="4" placeholder="Intro body" class="w-full border px-3 py-2 rounded">{{ old('intro_body', data_get($page, 'intro.body')) }}</textarea>
    </div>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save page</button>
</form>
@endsection
