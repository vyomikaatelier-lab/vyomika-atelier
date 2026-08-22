@extends('layouts.admin')
@section('title', 'Projects')
@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold">Projects</h1>
    <a href="{{ route('admin.projects.create') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Add Work Item</a>
</div>
<form method="GET" class="mb-4 flex gap-2 text-sm flex-wrap">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search project name…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded"><option value="">All</option><option value="published" @selected(request('status') === 'published')>Published</option><option value="draft" @selected(request('status') === 'draft')>Draft</option></select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($projects->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No project work items yet.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Project</th><th class="p-3">Work type</th><th class="p-3">City</th><th class="p-3">Client</th><th class="p-3">Order</th><th class="p-3">Status</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($projects as $project)<tr class="border-b">
        <td class="p-3">{{ $project->project_name }}</td><td class="p-3">{{ $project->work_type ?: '—' }}</td><td class="p-3">{{ $project->city ?: '—' }}</td><td class="p-3">{{ $project->client ?: '—' }}</td><td class="p-3">{{ $project->display_order }}</td>
        <td class="p-3">{{ $project->is_active ? 'Published' : 'Draft' }}</td>
        <td class="p-3"><a href="{{ route('admin.projects.edit', $project) }}" class="text-blue-600">Edit</a>
        <form action="{{ route('admin.projects.destroy', $project) }}" method="POST" class="inline" onsubmit="return confirm('Delete this work item?')">@csrf @method('DELETE')<button class="text-red-600">Delete</button></form></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $projects->links() }}</div>@endif
@endsection
