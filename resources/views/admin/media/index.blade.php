@extends('layouts.admin')
@section('title', 'Media')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Media Library</h1>
<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="bg-white p-4 rounded shadow mb-6 flex flex-wrap gap-3 items-end text-sm">
    @csrf
    <div><label class="block mb-1">Upload</label><input type="file" name="file" required accept="image/*,application/pdf"></div>
    <div><label class="block mb-1">Alt text</label><input name="alt" class="border px-3 py-2 rounded"></div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_private" value="1"> Private document</label>
    <button class="bg-gray-900 text-white px-4 py-2 rounded">Upload</button>
</form>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search filename…" class="border px-3 py-2 rounded">
    <select name="type" class="border px-3 py-2 rounded"><option value="">All types</option><option value="image" @selected(request('type')==='image')>Images</option><option value="pdf" @selected(request('type')==='pdf')>PDFs</option><option value="private" @selected(request('type')==='private')>Private</option></select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($media->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No files uploaded yet.</p>@else
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
@foreach($media as $file)
    <div class="bg-white rounded shadow p-3 text-sm">
        @if($file->isImage() && ! $file->is_private)
            <img src="{{ $file->url() }}" alt="" class="w-full h-32 object-cover rounded mb-2">
        @else
            <div class="h-32 bg-gray-100 rounded mb-2 flex items-center justify-center text-gray-500">{{ $file->is_private ? 'Private' : strtoupper(pathinfo($file->filename, PATHINFO_EXTENSION)) }}</div>
        @endif
        <p class="truncate font-medium" title="{{ $file->filename }}">{{ $file->filename }}</p>
        <p class="text-xs text-gray-500">{{ number_format($file->size / 1024, 1) }} KB · refs: {{ $file->referenceCount() }}</p>
        <form method="POST" action="{{ route('admin.media.update', $file) }}" class="mt-2 space-y-1">
            @csrf @method('PUT')
            <input name="alt" value="{{ $file->alt }}" placeholder="Alt text" class="w-full border px-2 py-1 rounded text-xs">
            <button class="text-blue-600 text-xs">Save alt</button>
        </form>
        <p class="mt-1"><a href="{{ route('admin.media.download', $file) }}" class="text-blue-600 text-xs">Download</a></p>
        @if($file->referenceCount() === 0)
        <form action="{{ route('admin.media.destroy', $file) }}" method="POST" class="mt-2" onsubmit="return confirm('Delete file?')">@csrf @method('DELETE')<button class="text-red-600 text-xs">Delete</button></form>
        @endif
    </div>
@endforeach
</div>
<div class="mt-4">{{ $media->links() }}</div>@endif
@endsection
