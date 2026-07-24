@extends('layouts.admin')
@section('title', 'Product Categories')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Product Categories</h1>
    <a href="{{ route('admin.categories.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Category</a>
</div>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded">
        <option value="">All statuses</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($categories->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No categories yet.</p>
@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Order</th><th class="p-3">Name</th><th class="p-3">Products</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($categories as $category)
        <tr class="border-b">
            <td class="p-3">{{ $category->sort_order }}</td>
            <td class="p-3">{{ $category->name }}</td>
            <td class="p-3">{{ $category->products_count }}</td>
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
