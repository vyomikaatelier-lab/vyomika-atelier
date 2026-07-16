@extends('layouts.admin')
@section('title', 'Blog')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Blog</h1>
    <a href="{{ route('admin.blog.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">New Post</a>
</div>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded"><option value="">All</option><option value="draft" @selected(request('status')==='draft')>Draft</option><option value="published" @selected(request('status')==='published')>Published</option></select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($posts->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No posts yet.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Title</th><th class="p-3">Author</th><th class="p-3">Status</th><th class="p-3">Published</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($posts as $post)<tr class="border-b">
        <td class="p-3">{{ $post->title }}</td><td class="p-3">{{ $post->author }}</td><td class="p-3">{{ ucfirst($post->status) }}</td><td class="p-3">{{ optional($post->published_at)->format('d M Y') }}</td>
        <td class="p-3"><a href="{{ route('admin.blog.edit', $post) }}" class="text-blue-600">Edit</a>
        <form action="{{ route('admin.blog.destroy', $post) }}" method="POST" class="inline" onsubmit="return confirm('Delete post?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $posts->links() }}</div>@endif
@endsection
