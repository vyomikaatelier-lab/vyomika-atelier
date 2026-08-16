@extends('layouts.admin')
@section('title', 'Collection Pages')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Collection Pages</h1>
    <a href="{{ route('admin.collection-pages.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Collection Page</a>
</div>
<p class="text-sm text-gray-600 mb-4">Edit hero, intro, and SEO overrides for shop collection landing pages. Activate, deactivate, or hide pages from the main menu in bulk.</p>
@if(session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif
@if($pages === [])
    <p class="text-gray-500 bg-white p-6 rounded shadow">No collection pages configured.</p>
@else
@if(\Illuminate\Support\Facades\Route::has('admin.collection-pages.bulk'))
<form method="POST" action="{{ route('admin.collection-pages.bulk') }}" id="collection-bulk-form" class="mb-3 flex flex-wrap items-center gap-2 text-sm bg-white rounded-lg shadow px-4 py-3">
    @csrf
    <label class="sr-only" for="collection-bulk-action">Bulk action</label>
    <select id="collection-bulk-action" name="action" required class="border border-gray-300 rounded px-3 py-2 bg-white min-w-[12rem]">
        <option value="">Bulk actions…</option>
        <option value="activate">Activate</option>
        <option value="deactivate">Deactivate</option>
        <option value="hide_from_nav">Hide from main menu</option>
        <option value="show_in_nav">Show in main menu</option>
        <option value="delete">Remove from site</option>
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Apply</button>
    <span id="collection-bulk-count" class="text-gray-500">0 selected</span>
</form>
@endif
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            @if(\Illuminate\Support\Facades\Route::has('admin.collection-pages.bulk'))
            <th class="p-3 w-10"><input type="checkbox" id="collection-select-all" aria-label="Select all collection pages"></th>
            @endif
            <th class="p-3">Slug</th>
            <th class="p-3">Title</th>
            <th class="p-3">Status</th>
            <th class="p-3">Menu</th>
            <th class="p-3"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($pages as $page)
        <tr class="border-b">
            @if(\Illuminate\Support\Facades\Route::has('admin.collection-pages.bulk'))
            <td class="p-3"><input type="checkbox" form="collection-bulk-form" name="slugs[]" value="{{ $page['slug'] }}" class="collection-bulk-check" aria-label="Select {{ $page['title'] }}"></td>
            @endif
            <td class="p-3 font-mono text-xs">{{ $page['slug'] }}</td>
            <td class="p-3">{{ $page['title'] }}</td>
            <td class="p-3">{{ $page['is_active'] ? 'Active' : 'Inactive' }}</td>
            <td class="p-3">{{ $page['hide_from_nav'] ? 'Hidden' : 'Visible' }}</td>
            <td class="p-3 space-x-2">
                <a href="{{ $page['storefront_url'] }}" target="_blank" rel="noopener" class="text-gray-600">View</a>
                <a href="{{ route('admin.collection-pages.edit', $page['slug']) }}" class="text-blue-600">Edit</a>
                @if(\Illuminate\Support\Facades\Route::has('admin.collection-pages.destroy'))
                <form action="{{ route('admin.collection-pages.destroy', $page['slug']) }}" method="POST" class="inline" onsubmit="return confirm('Remove this collection page from the site?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<script>
(function () {
    var bulkForm = document.getElementById('collection-bulk-form');
    var selectAll = document.getElementById('collection-select-all');
    var countEl = document.getElementById('collection-bulk-count');

    function updateBulkCount() {
        if (!countEl) return;
        var n = document.querySelectorAll('.collection-bulk-check:checked').length;
        countEl.textContent = n + ' selected';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.collection-bulk-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkCount();
        });
    }

    document.querySelectorAll('.collection-bulk-check').forEach(function (cb) {
        cb.addEventListener('change', updateBulkCount);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.collection-bulk-check:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Select at least one collection page.');
                return;
            }
            var action = bulkForm.querySelector('[name=action]').value;
            if (action === 'delete' && ! confirm('Remove selected collection pages from the site? Built-in pages are deactivated and hidden; custom pages are deleted when empty.')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@endif
@endsection
