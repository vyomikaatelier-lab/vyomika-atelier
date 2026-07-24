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
@if($services->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No services yet.</p>
@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Slug</th><th class="p-3">Designs</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($services as $service)
        <tr class="border-b">
            <td class="p-3">{{ $service->name }}</td>
            <td class="p-3">{{ $service->slug }}</td>
            <td class="p-3">{{ $service->designs_count }}</td>
            <td class="p-3">{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
            <td class="p-3 space-x-2">
                <a href="{{ route('admin.services.edit', $service) }}" class="text-blue-600">Edit</a>
                <form action="{{ route('admin.services.destroy', $service) }}" method="POST" class="inline" onsubmit="return confirm('Delete this service?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $services->links() }}</div>
@endif
@endsection
