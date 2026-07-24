@extends('layouts.admin')
@section('title', 'Edit '.$label.' hero')
@section('content')
<div class="mb-4 flex flex-wrap gap-3 items-center justify-between">
    <a href="{{ route('admin.page-heroes.index') }}" class="text-sm text-blue-600">← Back</a>
    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="text-sm border px-3 py-1.5 rounded">Preview public page ↗</a>
</div>
<h1 class="text-2xl font-semibold mb-2">{{ $label }}</h1>
<p class="text-sm text-gray-600 mb-6">{{ \App\Support\ResponsiveHero::adminUploadIntro() }}</p>

<form method="POST" action="{{ route('admin.page-heroes.update', $slug) }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">
    @csrf @method('PUT')
    <input name="hero_label" value="{{ old('hero_label', $page['label'] ?? '') }}" placeholder="Eyebrow / label (optional)" class="w-full border px-3 py-2 rounded">
    <input name="hero_title" value="{{ old('hero_title', $page['title'] ?? '') }}" placeholder="Hero title (optional)" class="w-full border px-3 py-2 rounded">
    <textarea name="hero_subtitle" rows="2" placeholder="Hero subtitle (optional)" class="w-full border px-3 py-2 rounded">{{ old('hero_subtitle', $page['subtitle'] ?? '') }}</textarea>
    @include('admin.partials.responsive-hero-images', ['prefix' => 'hero', 'hero' => $page])
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save hero</button>
</form>
@endsection
