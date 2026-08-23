@extends('layouts.admin')
@section('title', 'Blog')
@section('content')
<div class="flex flex-wrap justify-between items-start gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-semibold">Blog</h1>
        <p class="text-sm text-gray-600 mt-1">Manage journal articles, SEO, and publishing.</p>
    </div>
    <a href="{{ route('admin.blog.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">New Post</a>
</div>

@if(session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('warning'))
<div class="bg-amber-100 text-amber-900 px-4 py-2 rounded mb-4 text-sm">{{ session('warning') }}</div>
@endif
@if(session('error'))
<div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
@endif

<form method="GET" class="bg-white rounded-lg shadow p-4 mb-4 grid md:grid-cols-5 gap-3 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search title, slug…" class="border px-3 py-2 rounded md:col-span-2">
    <select name="category" class="border px-3 py-2 rounded">
        <option value="">All categories</option>
        @foreach($categories as $category)
        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
        @endforeach
    </select>
    <select name="status" class="border px-3 py-2 rounded">
        <option value="">All statuses</option>
        <option value="draft" @selected(request('status')==='draft')>Draft</option>
        <option value="published" @selected(request('status')==='published')>Published</option>
        <option value="scheduled" @selected(request('status')==='scheduled')>Scheduled</option>
    </select>
    <select name="featured" class="border px-3 py-2 rounded">
        <option value="">Featured: any</option>
        <option value="1" @selected(request('featured')==='1')>Featured only</option>
        <option value="0" @selected(request('featured')==='0')>Not featured</option>
    </select>
    <select name="sort" class="border px-3 py-2 rounded">
        <option value="published_desc" @selected($sort === 'published_desc')>Newest published</option>
        <option value="published_asc" @selected($sort === 'published_asc')>Oldest published</option>
        <option value="updated_desc" @selected($sort === 'updated_desc')>Recently updated</option>
        <option value="title_asc" @selected($sort === 'title_asc')>Title A–Z</option>
    </select>
    <div class="md:col-span-5 flex gap-2">
        <button class="border px-3 py-2 rounded bg-gray-900 text-white">Filter</button>
        @if(request()->hasAny(['q','category','status','featured','sort']))
        <a href="{{ route('admin.blog.index') }}" class="border px-3 py-2 rounded">Clear</a>
        @endif
    </div>
</form>

@if($posts->isEmpty())
<p class="text-gray-500 bg-white p-6 rounded shadow">No posts yet.</p>
@else
<form method="POST" action="{{ route('admin.blog.bulk') }}" id="blog-bulk-form">
    @csrf
    <div class="flex flex-wrap gap-2 mb-3 text-sm">
        <select name="action" class="border px-3 py-2 rounded" required>
            <option value="">Bulk action…</option>
            <option value="publish">Publish</option>
            <option value="draft">Move to draft</option>
            <option value="schedule">Schedule</option>
            <option value="delete">Delete</option>
        </select>
        <label class="inline-flex items-center gap-2 border px-3 py-2 rounded">
            <input type="checkbox" name="confirm" value="1"> Confirm delete
        </label>
        <button type="submit" class="border px-3 py-2 rounded bg-gray-100">Apply</button>
    </div>
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="w-full text-sm min-w-[960px]">
            <thead class="border-b bg-gray-50">
                <tr class="text-left">
                    <th class="p-3 w-8"><span class="sr-only">Select</span></th>
                    <th class="p-3 w-16">Thumb</th>
                    <th class="p-3">Title</th>
                    <th class="p-3">Category</th>
                    <th class="p-3">Author</th>
                    <th class="p-3">Featured</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Published</th>
                    <th class="p-3">Updated</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posts as $post)
                <tr class="border-b align-top">
                    <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $post->id }}"></td>
                    <td class="p-3">
                        @if($post->imageUrl())
                        <img src="{{ $post->imageUrl() }}" alt="" class="w-12 h-12 object-cover rounded border">
                        @else
                        <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="p-3 font-medium">{{ $post->title }}</td>
                    <td class="p-3">{{ $post->category ?: '—' }}</td>
                    <td class="p-3">{{ $post->author }}</td>
                    <td class="p-3">{{ $post->is_featured ? 'Yes' : '—' }}</td>
                    <td class="p-3">
                        @php
                            $badge = match(true) {
                                $post->status === 'published' && $post->published_at?->isFuture() => ['Scheduled', 'bg-blue-100 text-blue-800'],
                                $post->status === 'scheduled' => ['Scheduled', 'bg-blue-100 text-blue-800'],
                                $post->status === 'published' => ['Published', 'bg-green-100 text-green-800'],
                                default => ['Draft', 'bg-gray-100 text-gray-700'],
                            };
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                    </td>
                    <td class="p-3">{{ optional($post->published_at)->format('d M Y H:i') ?: '—' }}</td>
                    <td class="p-3">{{ $post->updated_at?->format('d M Y') }}</td>
                    <td class="p-3 whitespace-nowrap space-x-2">
                        <a href="{{ route('admin.blog.preview', $post) }}" class="text-gray-700" target="_blank" rel="noopener">Preview</a>
                        <a href="{{ route('admin.blog.edit', $post) }}" class="text-blue-600">Edit</a>
                        <form action="{{ route('admin.blog.duplicate', $post) }}" method="POST" class="inline">@csrf<button class="text-gray-600">Duplicate</button></form>
                        <button type="button" class="text-red-600" onclick="if(confirm('Delete this post?')){document.getElementById('delete-post-{{ $post->id }}').submit()}">Delete</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>
@foreach($posts as $post)
<form id="delete-post-{{ $post->id }}" action="{{ route('admin.blog.destroy', $post) }}" method="POST" class="hidden">@csrf @method('DELETE')</form>
@endforeach
<div class="mt-4">{{ $posts->links() }}</div>
@endif
@endsection
