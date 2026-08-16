@extends('layouts.admin')
@section('title', 'Add Collection Page')
@section('content')
<div class="mb-4"><a href="{{ route('admin.collection-pages.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">Add Collection Page</h1>
<p class="text-sm text-gray-600 mb-4">Creates a new shop collection landing page, category, and main-menu link. You can edit hero and SEO content after saving.</p>
<form method="POST" action="{{ route('admin.collection-pages.store') }}" class="bg-white p-6 rounded shadow space-y-4 max-w-xl">
    @csrf
    <div>
        <label class="block text-sm mb-1" for="name">Display name</label>
        <input id="name" name="name" value="{{ old('name') }}" required class="w-full border px-3 py-2 rounded">
        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm mb-1" for="slug">URL slug (optional)</label>
        <input id="slug" name="slug" value="{{ old('slug') }}" placeholder="e.g. side-tables" class="w-full border px-3 py-2 rounded font-mono text-sm">
        <p class="text-xs text-gray-500 mt-1">Lowercase letters, numbers, and hyphens only. Generated from the name if left blank.</p>
        @error('slug')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Create collection page</button>
</form>
@endsection
