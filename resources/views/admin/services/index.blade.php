@extends('layouts.admin')
@section('title', 'Services')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Services</h1>
    <a href="{{ route('admin.services.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Service</a>
</div>
<form method="GET" class="mb-4 flex gap-2 text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if(session('success'))
<div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif
@if($services->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No services yet.</p>
@else
@if(\Illuminate\Support\Facades\Route::has('admin.services.bulk'))
<form method="POST" action="{{ route('admin.services.bulk') }}" id="service-bulk-form" class="mb-3 flex flex-wrap items-center gap-2 text-sm bg-white rounded-lg shadow px-4 py-3">
    @csrf
    <input type="hidden" name="q" value="{{ request('q') }}">
    <label class="sr-only" for="service-bulk-action">Bulk action</label>
    <select id="service-bulk-action" name="action" required class="border border-gray-300 rounded px-3 py-2 bg-white min-w-[12rem]">
        <option value="">Bulk actions…</option>
        <option value="activate">Activate</option>
        <option value="deactivate">Deactivate</option>
        <option value="hide_from_nav">Hide from main menu</option>
        <option value="show_in_nav">Show in main menu</option>
        <option value="delete">Delete</option>
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Apply</button>
    <span id="service-bulk-count" class="text-gray-500">0 selected</span>
</form>
@endif
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b">
        <tr class="text-left">
            @if(\Illuminate\Support\Facades\Route::has('admin.services.bulk'))
            <th class="p-3 w-10"><input type="checkbox" id="service-select-all" aria-label="Select all services on this page"></th>
            @endif
            <th class="p-3">Name</th>
            <th class="p-3">Slug</th>
            <th class="p-3">Designs</th>
            <th class="p-3">Status</th>
            <th class="p-3">Menu</th>
            <th class="p-3"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($services as $service)
        <tr class="border-b">
            @if(\Illuminate\Support\Facades\Route::has('admin.services.bulk'))
            <td class="p-3"><input type="checkbox" form="service-bulk-form" name="ids[]" value="{{ $service->id }}" class="service-bulk-check" aria-label="Select {{ $service->name }}"></td>
            @endif
            <td class="p-3">{{ $service->name }}</td>
            <td class="p-3">{{ $service->slug }}</td>
            <td class="p-3">{{ $service->designs_count }}</td>
            <td class="p-3">{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
            <td class="p-3">{{ ($service->hide_from_nav ?? false) ? 'Hidden' : 'Visible' }}</td>
            <td class="p-3 space-x-2">
                <a href="{{ route('admin.services.edit', $service) }}" class="text-blue-600">Edit</a>
                <form action="{{ route('admin.services.destroy', $service) }}" method="POST" class="inline" onsubmit="return confirm('Delete this service?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $services->links() }}</div>
<script>
(function () {
    var bulkForm = document.getElementById('service-bulk-form');
    var selectAll = document.getElementById('service-select-all');
    var countEl = document.getElementById('service-bulk-count');

    function updateBulkCount() {
        if (!countEl) return;
        var n = document.querySelectorAll('.service-bulk-check:checked').length;
        countEl.textContent = n + ' selected';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.service-bulk-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateBulkCount();
        });
    }

    document.querySelectorAll('.service-bulk-check').forEach(function (cb) {
        cb.addEventListener('change', updateBulkCount);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            var checked = document.querySelectorAll('.service-bulk-check:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Select at least one service.');
                return;
            }
            var action = bulkForm.querySelector('[name=action]').value;
            if (action === 'delete' && ! confirm('Delete selected services?')) {
                e.preventDefault();
            }
        });
    }
})();
</script>
@endif
@endsection
