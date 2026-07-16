@extends('layouts.admin')
@section('title', 'Exhibitions')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Exhibitions</h1>
    <a href="{{ route('admin.exhibitions.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Event</a>
</div>
@if($exhibitions->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No exhibitions yet. Run db:seed to load defaults.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Order</th><th class="p-3">Event</th><th class="p-3">City</th><th class="p-3">Year</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($exhibitions as $event)<tr class="border-b">
        <td class="p-3">{{ $event->sort_order }}</td><td class="p-3">{{ $event->name }}</td><td class="p-3">{{ $event->city }}</td><td class="p-3">{{ $event->year }}</td>
        <td class="p-3">{{ $event->is_active ? 'Active' : 'Hidden' }}</td>
        <td class="p-3"><form action="{{ route('admin.exhibitions.move', [$event, 'up']) }}" method="POST" class="inline">@csrf<button class="text-gray-600">↑</button></form>
        <form action="{{ route('admin.exhibitions.move', [$event, 'down']) }}" method="POST" class="inline">@csrf<button class="text-gray-600">↓</button></form>
        <a href="{{ route('admin.exhibitions.edit', $event) }}" class="text-blue-600">Edit</a>
        <form action="{{ route('admin.exhibitions.destroy', $event) }}" method="POST" class="inline" onsubmit="return confirm('Delete event?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $exhibitions->links() }}</div>@endif
@endsection
