@extends('layouts.admin')
@section('title', 'Edit '.$label)
@section('content')
<div class="mb-4"><a href="{{ route('admin.static-pages.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-2">{{ $label }} — SEO</h1>
@php
    $titleLen = strlen((string) old('meta_title', $page['meta_title'] ?? ''));
    $descLen = strlen((string) old('meta_description', $page['meta_description'] ?? ''));
@endphp
<form method="POST" action="{{ route('admin.static-pages.update', $slug) }}" class="space-y-4 max-w-3xl bg-white p-6 rounded shadow">
    @csrf @method('PUT')
    <div class="border rounded p-3 bg-gray-50 text-sm">
        <p class="text-green-800 font-medium">{{ old('meta_title', $page['meta_title'] ?? 'Title preview') }}</p>
        <p class="text-blue-700 text-xs">{{ url('/') }}…</p>
        <p class="text-gray-600">{{ old('meta_description', $page['meta_description'] ?? '') }}</p>
    </div>
    <div>
        <label class="block text-sm mb-1">SEO title <span class="text-gray-400">({{ $titleLen }} chars — aim ~50–60)</span></label>
        <input name="meta_title" value="{{ old('meta_title', $page['meta_title'] ?? '') }}" class="w-full border px-3 py-2 rounded">
    </div>
    <div>
        <label class="block text-sm mb-1">Meta description <span class="text-gray-400">({{ $descLen }} chars — aim ~140–160)</span></label>
        <textarea name="meta_description" rows="3" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $page['meta_description'] ?? '') }}</textarea>
    </div>
    <input name="primary_keyword" value="{{ old('primary_keyword', $page['primary_keyword'] ?? '') }}" placeholder="Primary keyword (editorial only — not a meta keywords tag)" class="w-full border px-3 py-2 rounded">
    <input name="h1" value="{{ old('h1', $page['h1'] ?? '') }}" placeholder="H1 / page heading" class="w-full border px-3 py-2 rounded">
    <textarea name="intro" rows="4" placeholder="Introduction" class="w-full border px-3 py-2 rounded">{{ old('intro', $page['intro'] ?? '') }}</textarea>
    <div class="grid md:grid-cols-2 gap-3">
        <input name="og_title" value="{{ old('og_title', $page['og_title'] ?? '') }}" placeholder="OG title" class="w-full border px-3 py-2 rounded">
        <input name="og_image" value="{{ old('og_image', $page['og_image'] ?? '') }}" placeholder="OG image URL" class="w-full border px-3 py-2 rounded">
    </div>
    <textarea name="og_description" rows="2" placeholder="OG description" class="w-full border px-3 py-2 rounded">{{ old('og_description', $page['og_description'] ?? '') }}</textarea>
    <input name="canonical" value="{{ old('canonical', $page['canonical'] ?? '') }}" placeholder="Canonical URL (blank = current URL)" class="w-full border px-3 py-2 rounded">
    <div>
        <label class="block text-sm mb-1">Indexing</label>
        <select name="robots" class="border px-3 py-2 rounded">
            <option value="index" @selected(old('robots', $page['robots'] ?? 'index') === 'index')>index, follow</option>
            <option value="noindex" @selected(old('robots', $page['robots'] ?? '') === 'noindex')>noindex</option>
        </select>
    </div>
    <div class="space-y-2">
        <h2 class="font-medium text-sm">FAQs</h2>
        @php $faqs = old('faq_q') ? array_map(fn ($q, $i) => ['q' => $q, 'a' => old('faq_a.'.$i)], old('faq_q', []), array_keys(old('faq_q', []))) : ($page['faqs'] ?? [['q'=>'','a'=>'']]); @endphp
        @foreach($faqs as $i => $faq)
        <input name="faq_q[]" value="{{ $faq['q'] ?? '' }}" placeholder="Question" class="w-full border px-3 py-2 rounded">
        <textarea name="faq_a[]" rows="2" placeholder="Answer" class="w-full border px-3 py-2 rounded mb-2">{{ $faq['a'] ?? '' }}</textarea>
        @endforeach
        <input name="faq_q[]" value="" placeholder="New question" class="w-full border px-3 py-2 rounded">
        <textarea name="faq_a[]" rows="2" placeholder="New answer" class="w-full border px-3 py-2 rounded"></textarea>
    </div>
    <button class="bg-gray-900 text-white px-5 py-2 rounded text-sm">Save</button>
</form>
@endsection
