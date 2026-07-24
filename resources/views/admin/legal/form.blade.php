@extends('layouts.admin')
@section('title', 'Edit '.$page->title)
@section('content')
<div class="mb-4"><a href="{{ route('admin.legal.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $page->title }}</h1>
@php
    $sections = old('section_headings') !== null
        ? collect(old('section_headings', []))->map(fn ($heading, $i) => [
            'heading' => $heading,
            'paragraphs' => array_values(array_filter(preg_split('/\r\n|\r|\n/', old('section_paragraphs.'.$i, '')) ?: [])),
        ])
        : collect($page->sections ?? []);
@endphp
<form method="POST" action="{{ route('admin.legal.update', $page) }}" class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">
    @csrf @method('PUT')
    <div><label class="block text-sm mb-1">Title</label><input name="title" value="{{ old('title', $page->title) }}" required class="w-full border px-3 py-2 rounded"></div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Last updated</label><input type="date" name="content_updated_at" value="{{ old('content_updated_at', optional($page->content_updated_at)->format('Y-m-d')) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page->meta_description) }}</textarea></div>

    <div class="space-y-4 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Sections</p>
        @foreach($sections as $index => $section)
        <div class="border rounded p-4 bg-white space-y-2">
            <div><label class="block text-sm mb-1">Heading</label><input name="section_headings[]" value="{{ $section['heading'] ?? '' }}" class="w-full border px-3 py-2 rounded"></div>
            <div><label class="block text-sm mb-1">Paragraphs (one per line)</label><textarea name="section_paragraphs[]" rows="4" class="w-full border px-3 py-2 rounded text-sm">{{ implode("\n", $section['paragraphs'] ?? []) }}</textarea></div>
        </div>
        @endforeach
        <div class="border rounded p-4 bg-white space-y-2 border-dashed">
            <div><label class="block text-sm mb-1">Add heading</label><input name="section_headings[]" class="w-full border px-3 py-2 rounded"></div>
            <div><label class="block text-sm mb-1">Add paragraphs</label><textarea name="section_paragraphs[]" rows="4" class="w-full border px-3 py-2 rounded text-sm"></textarea></div>
        </div>
    </div>

    <details class="border rounded p-4">
        <summary class="text-sm font-medium cursor-pointer">Advanced: edit raw JSON</summary>
        <div class="mt-3"><label class="block text-sm mb-1">Sections JSON</label><p class="text-xs text-gray-500 mb-1">If filled, this overrides the structured fields above.</p>
            <textarea name="sections_json" rows="12" class="w-full border px-3 py-2 rounded font-mono text-xs">{{ old('sections_json') }}</textarea>
        </div>
    </details>

    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save page</button>
</form>
@endsection
