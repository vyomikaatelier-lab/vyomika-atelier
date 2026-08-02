@extends('layouts.admin')
@section('title', 'Edit '.$label.' hero')
@section('content')
<div class="mb-4 flex flex-wrap gap-3 items-center justify-between">
    <a href="{{ route('admin.page-heroes.index') }}" class="text-sm text-blue-600">← Back</a>
    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="text-sm border px-3 py-1.5 rounded">Preview public page ↗</a>
</div>
<h1 class="text-2xl font-semibold mb-2">{{ $label }}</h1>
@if(request('saved') || session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') ?: 'Hero saved successfully.' }}</div>
@endif
@if(data_get($stored, 'title'))
<p class="text-xs text-gray-500 mb-4">Database hero title: <strong>{{ data_get($stored, 'title') }}</strong></p>
@endif
<form method="POST" action="{{ route('admin.page-heroes.update', $slug) }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">
    @csrf @method('PUT')
    <input type="hidden" name="_page_save" value="1">
    @php
        $heroContext = \App\Support\HeroAdminFields::uploadContext(
            $page,
            str_starts_with($slug, 'service:') ? 'compact' : 'cover'
        );
    @endphp
    @include('admin.partials.hero-fields', ['prefix' => 'hero', 'hero' => $page, 'context' => $heroContext])
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save hero</button>
</form>
@endsection
