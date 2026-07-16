@extends('layouts.admin')
@section('title', 'Edit '.$page->title)
@section('content')
<div class="mb-4"><a href="{{ route('admin.legal.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $page->title }}</h1>
<form method="POST" action="{{ route('admin.legal.update', $page) }}" class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">
    @csrf @method('PUT')
    <div><label class="block text-sm mb-1">Title</label><input name="title" value="{{ old('title', $page->title) }}" required class="w-full border px-3 py-2 rounded"></div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Last updated</label><input type="date" name="content_updated_at" value="{{ old('content_updated_at', optional($page->content_updated_at)->format('Y-m-d')) }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page->meta_description) }}</textarea></div>
    <div><label class="block text-sm mb-1">Sections JSON</label><p class="text-xs text-gray-500 mb-1">Array of objects with <code>heading</code> and <code>paragraphs</code> (string array).</p>
        <textarea name="sections_json" rows="18" class="w-full border px-3 py-2 rounded font-mono text-xs">{{ old('sections_json', json_encode($page->sections ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
    </div>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save page</button>
</form>
@endsection
