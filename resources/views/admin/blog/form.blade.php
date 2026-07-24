@extends('layouts.admin')
@section('title', isset($post) ? 'Edit Post' : 'New Post')
@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ isset($post) ? 'Edit' : 'New' }} Blog Post</h1>
<form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded shadow space-y-4 max-w-3xl">
    @csrf @if(isset($post)) @method('PUT') @endif
    <div><label class="block text-sm mb-1">Title</label><input name="title" value="{{ old('title', $post->title ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Excerpt</label><textarea name="excerpt" rows="2" class="w-full border px-3 py-2 rounded">{{ old('excerpt', $post->excerpt ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Body</label><textarea name="content" rows="10" class="w-full border px-3 py-2 rounded font-mono text-sm">{{ old('content', $post->content ?? '') }}</textarea></div>
    <div class="grid md:grid-cols-3 gap-4">
        <div><label class="block text-sm mb-1">Category</label><input name="category" value="{{ old('category', $post->category ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Author</label><input name="author" value="{{ old('author', $post->author ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Publish date</label><input type="datetime-local" name="published_at" value="{{ old('published_at', isset($post->published_at) ? $post->published_at->format('Y-m-d\TH:i') : '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">Hero image</p>
        @if(isset($post) && $post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="" class="w-40 h-28 object-cover rounded border">
        @endif
        <div><label class="block text-sm mb-1">Hero image URL</label><input name="image" value="{{ old('image', $post->image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Upload hero</label><input type="file" name="image_file" accept="image/*"></div>
        <div><label class="block text-sm mb-1">Hero alt text</label><input name="hero_image_alt" value="{{ old('hero_image_alt', $post->hero_image_alt ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    @include('admin.partials.gallery-upload-fields', ['gallery' => isset($post) ? $post->gallery : null, 'directory' => 'blog'])
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $post->meta_title ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Canonical URL</label><input name="canonical_url" value="{{ old('canonical_url', $post->canonical_url ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $post->meta_description ?? '') }}</textarea></div>
    @php
        $selectedProducts = old('related_product_slugs', isset($post) ? ($post->related_product_slugs ?? []) : []);
        $selectedProjects = old('related_project_slugs', isset($post) ? ($post->related_project_slugs ?? []) : []);
        $selectedServices = old('related_service_slugs', isset($post) ? ($post->related_service_slugs ?? []) : []);
        $faqItems = old('faq_questions') !== null
            ? collect(old('faq_questions', []))->map(fn ($q, $i) => ['question' => $q, 'answer' => old('faq_answers.'.$i)])
            : collect(isset($post) ? $post->faqItems() : []);
    @endphp
    <div><label class="block text-sm mb-1">Related products</label><select name="related_product_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($products as $slug => $name)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedProducts, true))>{{ $name }} ({{ $slug }})</option>@endforeach</select></div>
    <div><label class="block text-sm mb-1">Related projects</label><select name="related_project_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($projects as $slug => $title)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedProjects, true))>{{ $title }} ({{ $slug }})</option>@endforeach</select></div>
    <div><label class="block text-sm mb-1">Related services</label><select name="related_service_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($services as $slug => $name)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedServices, true))>{{ $name }} ({{ $slug }})</option>@endforeach</select></div>
    <div class="space-y-3 border rounded p-4 bg-gray-50">
        <p class="text-sm font-medium">FAQ items</p>
        @forelse($faqItems as $index => $item)
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3 bg-white">
            <div><label class="block text-xs mb-1">Question</label><input name="faq_questions[]" value="{{ $item['question'] ?? '' }}" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm">{{ $item['answer'] ?? '' }}</textarea></div>
        </div>
        @empty
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3 bg-white">
            <div><label class="block text-xs mb-1">Question</label><input name="faq_questions[]" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm"></textarea></div>
        </div>
        @endforelse
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3 bg-white border-dashed">
            <div><label class="block text-xs mb-1">Add question</label><input name="faq_questions[]" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Add answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm"></textarea></div>
        </div>
    </div>
    <div><label class="block text-sm mb-1">Status</label><select name="status" class="border px-3 py-2 rounded"><option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status', $post->status ?? '') === 'published')>Published</option></select></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured ?? false))> Featured</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $post->is_active ?? true))> Visible on site</label>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
