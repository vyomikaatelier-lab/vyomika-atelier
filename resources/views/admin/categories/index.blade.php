@extends('layouts.admin')
@section('title', 'Product Categories')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Product Categories</h1>
    <div class="flex gap-2">
        <form action="{{ route('admin.categories.sync') }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="border px-4 py-2 rounded text-sm">Sync canonical categories</button>
        </form>
        <a href="{{ route('admin.categories.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Category</a>
    </div>
</div>

<div class="mb-4 p-4 bg-blue-50 border border-blue-100 rounded text-sm text-blue-900">
    <p class="font-medium mb-1">Flat taxonomy — no nested parent categories</p>
    <p>Each category is a top-level parent linked to one storefront section: <strong>Shop</strong>, <strong>Studio</strong>, or <strong>Railings</strong>. Products pick a category and section; Studio header navigation uses Services separately.</p>
    <p class="mt-1 text-blue-800">CLI: <code class="bg-white px-1 rounded">php artisan catalog:sync-categories --assign-products</code></p>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <select name="section" class="border px-3 py-2 rounded">
        <option value="">All sections</option>
        @foreach($sectionLabels as $value => $label)
            <option value="{{ $value }}" @selected(request('section') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="status" class="border px-3 py-2 rounded">
        <option value="">All statuses</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($categories->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No categories yet. Use <strong>Sync canonical categories</strong> to seed defaults.</p>
@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            <th class="p-3">Order</th>
            <th class="p-3">Name</th>
            <th class="p-3">Section</th>
            <th class="p-3">Slug</th>
            <th class="p-3">Products</th>
            <th class="p-3">Storefront</th>
            <th class="p-3">Status</th>
            <th class="p-3"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories as $category)
        <tr class="border-b">
            <td class="p-3">{{ $category->sort_order }}</td>
            <td class="p-3">{{ $category->name }}</td>
            <td class="p-3">
                @php $resolved = $category->resolvedSection(); @endphp
                {{ $resolved ? ($sectionLabels[$resolved] ?? ucfirst($resolved)) : '—' }}
            </td>
            <td class="p-3 font-mono text-xs">{{ $category->slug }}</td>
            <td class="p-3">{{ $category->products_count }}</td>
            <td class="p-3">
                @if($url = $category->storefrontUrl())
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600">{{ $category->storefrontLinkLabel() }}</a>
                @else
                    <span class="text-gray-400">{{ $category->storefrontLinkLabel() }}</span>
                @endif
            </td>
            <td class="p-3">{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
            <td class="p-3 space-x-2">
                <form action="{{ route('admin.categories.move', [$category, 'up']) }}" method="POST" class="inline">@csrf<button class="text-gray-600" title="Move up">↑</button></form>
                <form action="{{ route('admin.categories.move', [$category, 'down']) }}" method="POST" class="inline">@csrf<button class="text-gray-600" title="Move down">↓</button></form>
                <a href="{{ route('admin.categories.edit', $category) }}" class="text-blue-600">Edit</a>
                @if($category->products_count === 0)
                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $categories->links() }}</div>
@endif
@endsection
