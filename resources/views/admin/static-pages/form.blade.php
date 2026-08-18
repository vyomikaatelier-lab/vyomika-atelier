@extends('layouts.admin')
@section('title', 'Edit '.$label)
@section('content')
<div class="mb-4"><a href="{{ route('admin.static-pages.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-2">{{ $label }} — SEO</h1>
<form method="POST" action="{{ route('admin.static-pages.update', $slug) }}" class="space-y-4 max-w-3xl bg-white p-6 rounded shadow">
    @csrf @method('PUT')
    @include('admin.partials.seo-fields', [
        'page' => $page,
        'previewUrl' => url('/'.($slug === 'home' ? '' : $slug)),
        'previewTitleFallback' => $label,
    ])
    <input name="h1" value="{{ old('h1', $page['h1'] ?? '') }}" placeholder="H1 / page heading" class="w-full border px-3 py-2 rounded">
    <textarea name="intro" rows="4" placeholder="Introduction" class="w-full border px-3 py-2 rounded">{{ old('intro', $page['intro'] ?? '') }}</textarea>
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
    <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded text-sm">Save</button>
</form>
@endsection
