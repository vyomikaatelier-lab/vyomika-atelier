@extends('layouts.admin')
@section('title', isset($post) ? 'Edit Post' : 'New Post')
@section('content')
<h1 class="text-2xl font-semibold mb-2">{{ isset($post) ? 'Edit' : 'New' }} Blog Post</h1>
<p class="text-sm text-gray-600 mb-6">Editorial workspace — content, media, SEO, relationships, FAQs, and publishing.</p>

@if($errors->any())
<div class="bg-red-50 text-red-800 px-4 py-3 rounded mb-4 text-sm">
    <ul class="list-disc pl-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
@if(session('error'))
<div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
@endif

<form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data" class="max-w-5xl" data-blog-form>
    @csrf @if(isset($post)) @method('PUT') @endif
    <input type="hidden" name="_page_save" value="1">

    <div class="flex flex-wrap gap-2 mb-4 border-b pb-2" role="tablist">
        @foreach(['content' => 'Content', 'media' => 'Media', 'seo' => 'SEO', 'relationships' => 'Relationships', 'faqs' => 'FAQs', 'publishing' => 'Publishing'] as $tab => $label)
        <button type="button" class="blog-tab px-3 py-1.5 rounded text-sm border {{ $loop->first ? 'bg-gray-900 text-white border-gray-900' : 'bg-white' }}" data-tab="{{ $tab }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="blog-panel space-y-4 bg-white p-6 rounded shadow" data-panel="content">
        <div><label class="block text-sm mb-1 font-medium">Title *</label><input name="title" value="{{ old('title', $post->title ?? '') }}" required class="w-full border px-3 py-2 rounded"></div>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Slug</label>
                <input name="slug" value="{{ old('slug', $post->slug ?? '') }}" placeholder="Auto from title" class="w-full border px-3 py-2 rounded font-mono text-sm">
                <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate. Changing a published slug affects live URLs.</p>
            </div>
            <div>
                <label class="block text-sm mb-1">Category</label>
                <select name="category" class="w-full border px-3 py-2 rounded">
                    <option value="">— Select —</option>
                    @foreach($categories as $slug => $label)
                    <option value="{{ $label }}" @selected(old('category', $post->category ?? '') === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div><label class="block text-sm mb-1">Excerpt</label><textarea name="excerpt" rows="2" class="w-full border px-3 py-2 rounded">{{ old('excerpt', $post->excerpt ?? '') }}</textarea></div>
        <div><label class="block text-sm mb-1">Author</label><input name="author" value="{{ old('author', $post->author ?? \App\Models\BlogPost::DEFAULT_AUTHOR) }}" class="w-full border px-3 py-2 rounded"></div>
        <div>
            <label class="block text-sm mb-1 font-medium">Body</label>
            <textarea name="content" rows="14" class="w-full border px-3 py-2 rounded font-mono text-sm" data-blog-body>{{ old('content', $post->content ?? '') }}</textarea>
            <p class="text-xs text-gray-500 mt-1">Allowed HTML: headings, paragraphs, lists, links, tables, blockquotes, images.</p>
        </div>
        <p class="text-sm text-gray-600">Reading time preview: <strong data-reading-time>{{ isset($post) ? $post->readingTime() : 1 }}</strong> min (auto-calculated at 200 wpm)</p>
        @if(isset($post))
        <a href="{{ route('admin.blog.preview', $post) }}" target="_blank" rel="noopener" class="inline-block text-sm text-blue-600">Preview article →</a>
        @endif
    </div>

    <div class="blog-panel hidden space-y-4 bg-white p-6 rounded shadow mt-4" data-panel="media">
        <div class="space-y-3 border rounded p-4 bg-gray-50">
            <p class="text-sm font-medium">Hero image</p>
            @if(isset($post) && $post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="" class="w-48 h-32 object-cover rounded border">
            @endif
            <div><label class="block text-sm mb-1">Hero image URL</label><input name="image" value="{{ old('image', $post->image ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
            <div><label class="block text-sm mb-1">Upload hero</label><input type="file" name="image_file" accept="image/jpeg,image/jpg,image/png,image/webp"></div>
            <div><label class="block text-sm mb-1">Hero alt text * (required to publish)</label><input name="hero_image_alt" value="{{ old('hero_image_alt', $post->hero_image_alt ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
            <div><label class="block text-sm mb-1">Hero caption</label><input name="hero_image_caption" value="{{ old('hero_image_caption', $post->hero_image_caption ?? '') }}" class="w-full border px-3 py-2 rounded"></div>
        </div>
        @php
            $galleryItems = old('gallery_existing') !== null
                ? array_values(array_filter((array) old('gallery_existing')))
                : (isset($post) ? ($post->gallery ?? []) : []);
            $galleryMeta = isset($post) ? ($post->gallery_meta ?? []) : [];
        @endphp
        <div class="space-y-3 border rounded p-4 bg-gray-50">
            <p class="text-sm font-medium">Gallery</p>
            @forelse($galleryItems as $index => $path)
            <div class="border rounded p-3 bg-white grid md:grid-cols-3 gap-3">
                <img src="{{ \App\Support\MediaUrl::resolve($path) }}" alt="" class="w-28 h-20 object-cover rounded border">
                <input type="hidden" name="gallery_existing[]" value="{{ $path }}">
                <div><label class="block text-xs mb-1">Alt</label><input name="gallery_alt[]" value="{{ old('gallery_alt.'.$index, $galleryMeta[$index]['alt'] ?? '') }}" class="w-full border px-2 py-1 rounded text-sm"></div>
                <div><label class="block text-xs mb-1">Caption</label><input name="gallery_caption[]" value="{{ old('gallery_caption.'.$index, $galleryMeta[$index]['caption'] ?? '') }}" class="w-full border px-2 py-1 rounded text-sm"></div>
            </div>
            @empty
            <p class="text-xs text-gray-500">No gallery images yet.</p>
            @endforelse
            @include('admin.partials.gallery-upload-fields', ['gallery' => null, 'directory' => 'blog', 'label' => 'Add gallery images'])
        </div>
    </div>

    <div class="blog-panel hidden space-y-4 bg-white p-6 rounded shadow mt-4" data-panel="seo">
        @php
            $seoPage = [
                'meta_title' => old('meta_title', $post->meta_title ?? ''),
                'meta_description' => old('meta_description', $post->meta_description ?? ''),
                'primary_keyword' => old('primary_keyword', $post->primary_keyword ?? ''),
                'og_title' => old('og_title', $post->og_title ?? ''),
                'og_description' => old('og_description', $post->og_description ?? ''),
                'og_image' => old('og_image', $post->og_image ?? ''),
                'canonical' => old('canonical', $post->canonical_url ?? ''),
                'robots' => old('robots', ($post->robots_index ?? true) ? 'index' : 'noindex'),
            ];
        @endphp
        @include('admin.partials.seo-fields', [
            'page' => $seoPage,
            'previewUrl' => isset($post) ? route('blog.show', $post->slug) : route('blog.index'),
            'previewTitleFallback' => old('title', $post->title ?? 'Blog post'),
            'showSeoKeyword' => true,
        ])
        @if(isset($post) && $post->status === 'published' && old('robots', ($post->robots_index ?? true) ? 'index' : 'noindex') === 'noindex')
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">This published article is set to noindex — it will not appear in search results.</p>
        @endif
    </div>

    <div class="blog-panel hidden space-y-4 bg-white p-6 rounded shadow mt-4" data-panel="relationships">
        @php
            $selectedProducts = old('related_product_slugs', isset($post) ? ($post->related_product_slugs ?? []) : []);
            $selectedProjects = old('related_project_ids', isset($post) ? ($post->related_project_ids ?? []) : []);
            $selectedServices = old('related_service_slugs', isset($post) ? ($post->related_service_slugs ?? []) : []);
            $selectedArticles = old('related_article_slugs', isset($post) ? ($post->related_article_slugs ?? []) : []);
        @endphp
        <p class="text-xs text-gray-500">Hold Ctrl/Cmd to select multiple. Related articles auto-fill from the same category when none are selected on the storefront.</p>
        <div><label class="block text-sm mb-1">Related products</label><select name="related_product_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($products as $slug => $name)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedProducts, true))>{{ $name }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Related projects</label><select name="related_project_ids[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($projects as $id => $name)<option value="{{ $id }}" @selected(in_array((int) $id, array_map('intval', (array) $selectedProjects), true))>{{ $name }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Related services</label><select name="related_service_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($services as $slug => $name)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedServices, true))>{{ $name }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Related articles</label><select name="related_article_slugs[]" multiple class="w-full border px-3 py-2 rounded min-h-[120px]">@foreach($articles as $slug => $title)<option value="{{ $slug }}" @selected(in_array($slug, (array) $selectedArticles, true))>{{ $title }}</option>@endforeach</select></div>
    </div>

    <div class="blog-panel hidden space-y-4 bg-white p-6 rounded shadow mt-4" data-panel="faqs">
        @php
            $faqItems = old('faq_questions') !== null
                ? collect(old('faq_questions', []))->map(fn ($q, $i) => ['question' => $q, 'answer' => old('faq_answers.'.$i)])
                : collect(isset($post) ? $post->faqItems() : []);
        @endphp
        <p class="text-xs text-gray-500">Empty rows are ignored. Both question and answer are required for each FAQ.</p>
        @forelse($faqItems as $item)
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3">
            <div><label class="block text-xs mb-1">Question</label><input name="faq_questions[]" value="{{ $item['question'] ?? '' }}" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm">{{ $item['answer'] ?? '' }}</textarea></div>
        </div>
        @empty
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3">
            <div><label class="block text-xs mb-1">Question</label><input name="faq_questions[]" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm"></textarea></div>
        </div>
        @endforelse
        <div class="grid md:grid-cols-2 gap-3 border rounded p-3 border-dashed">
            <div><label class="block text-xs mb-1">Add question</label><input name="faq_questions[]" class="w-full border px-3 py-2 rounded text-sm"></div>
            <div><label class="block text-xs mb-1">Add answer</label><textarea name="faq_answers[]" rows="2" class="w-full border px-3 py-2 rounded text-sm"></textarea></div>
        </div>
    </div>

    <div class="blog-panel hidden space-y-4 bg-white p-6 rounded shadow mt-4" data-panel="publishing">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1 font-medium">Status</label>
                <select name="status" class="w-full border px-3 py-2 rounded">
                    <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option>
                    <option value="published" @selected(old('status', $post->status ?? '') === 'published')>Published</option>
                    <option value="scheduled" @selected(old('status', $post->status ?? '') === 'scheduled')>Scheduled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm mb-1">Publish / schedule date</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', isset($post->published_at) ? $post->published_at->format('Y-m-d\TH:i') : '') }}" class="w-full border px-3 py-2 rounded">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured ?? false))> Featured (only one featured article at a time)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $post->is_active ?? true))> Visible on site when published</label>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" name="action" value="draft" class="border px-4 py-2 rounded text-sm">Save draft</button>
            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
            @if(isset($post))
            <a href="{{ route('admin.blog.preview', $post) }}" target="_blank" rel="noopener" class="border px-4 py-2 rounded text-sm">Preview</a>
            @endif
        </div>
    </div>
</form>

<script>
(function () {
    var tabs = document.querySelectorAll('.blog-tab');
    var panels = document.querySelectorAll('.blog-panel');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var name = tab.getAttribute('data-tab');
            tabs.forEach(function (t) {
                t.classList.toggle('bg-gray-900', t === tab);
                t.classList.toggle('text-white', t === tab);
                t.classList.toggle('border-gray-900', t === tab);
                t.classList.toggle('bg-white', t !== tab);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-panel') !== name);
            });
        });
    });

    var body = document.querySelector('[data-blog-body]');
    var reading = document.querySelector('[data-reading-time]');
    if (body && reading) {
        var updateReading = function () {
            var text = body.value.replace(/<[^>]+>/g, ' ').trim();
            var words = text ? text.split(/\s+/).length : 0;
            reading.textContent = Math.max(1, Math.ceil(words / 200));
        };
        body.addEventListener('input', updateReading);
    }
})();
</script>
@endsection
