@extends('layouts.admin')
@section('title', 'Product Categories')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Product Categories</h1>
    <div class="flex gap-2">
        <form action="{{ route('admin.categories.sync') }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="border px-4 py-2 rounded text-sm">Sync categories</button>
        </form>
        <form action="{{ route('admin.categories.sync') }}" method="POST" class="inline">
            @csrf
            <input type="hidden" name="assign_products" value="1">
            <button type="submit" class="border px-4 py-2 rounded text-sm">Sync + assign products</button>
        </form>
        <a href="{{ route('admin.categories.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Category</a>
    </div>
</div>

<div class="mb-4 p-4 bg-blue-50 border border-blue-100 rounded text-sm text-blue-900">
    <p class="font-medium mb-1">Flat taxonomy — no nested parent categories</p>
    <p>Each category is a top-level parent linked to one storefront section: <strong>Shop</strong>, <strong>Studio</strong>, or <strong>Railings</strong>. Products pick a category and section; Studio header navigation uses Services separately.</p>
    <p class="mt-1 text-blue-800">Use <strong>Sync + assign products</strong> to link unclassified products to categories. CLI equivalent: <code class="bg-white px-1 rounded">php artisan catalog:sync-categories --assign-products</code></p>
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
        <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        <option value="all" @selected(request('status') === 'all')>All statuses</option>
    </select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if(session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif
@if($categories->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No categories yet. Use <strong>Sync canonical categories</strong> to seed defaults.</p>
@else
<form method="POST" action="{{ route('admin.categories.bulk') }}" id="category-bulk-form" class="mb-3 flex flex-wrap items-center gap-2 text-sm bg-white rounded-lg shadow px-4 py-3">
    @csrf
    <input type="hidden" name="q" value="{{ request('q') }}">
    <input type="hidden" name="section" value="{{ request('section') }}">
    <input type="hidden" name="status" value="{{ request('status', 'active') }}">
    <label class="sr-only" for="category-bulk-action">Bulk action</label>
    <select id="category-bulk-action" name="action" required class="border border-gray-300 rounded px-3 py-2 bg-white min-w-[12rem]">
        <option value="">Bulk actions…</option>
        <option value="activate">Activate</option>
        <option value="deactivate">Deactivate</option>
        <option value="hide_when_unavailable">Hide when nothing in stock</option>
        <option value="show_when_unavailable">Keep visible when nothing in stock</option>
        <option value="delete">Delete empty categories</option>
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Apply</button>
    <span id="category-bulk-count" class="text-gray-500">0 selected</span>
</form>
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            <th class="p-3 w-10"><input type="checkbox" id="category-select-all" aria-label="Select all categories on this page"></th>
            <th class="p-3 w-10">#</th>
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
            <td class="p-3"><input type="checkbox" form="category-bulk-form" name="ids[]" value="{{ $category->id }}" class="category-bulk-check" aria-label="Select {{ $category->name }}"></td>
            <td class="p-3 text-gray-500 tabular-nums">{{ $categories->firstItem() + $loop->index }}</td>
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
                @foreach(['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                <form action="{{ route('admin.categories.move', [$category, $direction]) }}" method="POST" class="inline">
                    @csrf
                    {{-- Forwarded so the neighbour is the row visible above/below this one. --}}
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input type="hidden" name="status" value="{{ request('status', 'active') }}">
                    <input type="hidden" name="section" value="{{ request('section') }}">
                    <button class="text-gray-600" title="Move {{ $direction }}">{{ $arrow }}</button>
                </form>
                @endforeach
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
<script>
(function () {
    var bulkForm = document.getElementById('category-bulk-form');
    var selectAll = document.getElementById('category-select-all');
    var countEl = document.getElementById('category-bulk-count');

    function updateBulkCount() {
        if (!countEl) return;
        var n = document.querySelectorAll('.category-bulk-check:checked').length;
        countEl.textContent = n + ' selected';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.category-bulk-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkCount();
        });
    }

    document.querySelectorAll('.category-bulk-check').forEach(function (cb) {
        cb.addEventListener('change', updateBulkCount);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.category-bulk-check:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Select at least one category.');
                return;
            }
            var action = bulkForm.querySelector('[name=action]').value;
            if (action === 'delete' && ! confirm('Delete selected empty categories? Categories with products will be skipped.')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@endif
@endsection
