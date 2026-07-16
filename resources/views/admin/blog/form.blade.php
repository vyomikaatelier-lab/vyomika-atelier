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
    <div><label class="block text-sm mb-1">Hero image URL</label><input name="image" value="{{ old('image', $post->image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Upload hero</label><input type="file" name="image_file" accept="image/*"></div>
    <div><label class="block text-sm mb-1">Hero alt text</label><input name="hero_image_alt" value="{{ old('hero_image_alt', $post->hero_image_alt ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div class="grid md:grid-cols-2 gap-4">
        <div><label class="block text-sm mb-1">SEO title</label><input name="meta_title" value="{{ old('meta_title', $post->meta_title ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        <div><label class="block text-sm mb-1">Canonical URL</label><input name="canonical_url" value="{{ old('canonical_url', $post->canonical_url ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
    </div>
    <div><label class="block text-sm mb-1">Meta description</label><textarea name="meta_description" rows="2" class="w-full border px-3 py-2 rounded">{{ old('meta_description', $post->meta_description ?? '') }}</textarea></div>
    <div><label class="block text-sm mb-1">Related product slugs (comma-separated)</label><input name="related_product_slugs" value="{{ old('related_product_slugs', isset($post) && is_array($post->related_product_slugs) ? implode(', ', $post->related_product_slugs) : '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Related project slugs</label><input name="related_project_slugs" value="{{ old('related_project_slugs', isset($post) && is_array($post->related_project_slugs) ? implode(', ', $post->related_project_slugs) : '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Related service slugs</label><input name="related_service_slugs" value="{{ old('related_service_slugs', isset($post) && is_array($post->related_service_slugs) ? implode(', ', $post->related_service_slugs) : '') }}" class="w-full border px-3 py-2 rounded"></div>
    <div><label class="block text-sm mb-1">Status</label><select name="status" class="border px-3 py-2 rounded"><option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status', $post->status ?? '') === 'published')>Published</option></select></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured ?? false))> Featured</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $post->is_active ?? true))> Visible on site</label>
    <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
</form>
@endsection
