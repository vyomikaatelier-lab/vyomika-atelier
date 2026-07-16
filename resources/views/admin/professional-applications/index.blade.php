@extends('layouts.admin')
@section('title', 'Professional Applications')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Professional Applications</h1>
<form method="GET" class="mb-4 flex gap-2 text-sm flex-wrap">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($applications->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No applications yet.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Status</th><th class="p-3">Submitted</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($applications as $app)<tr class="border-b">
        <td class="p-3">{{ $app->name }}</td><td class="p-3">{{ $app->email }}</td><td class="p-3">{{ $app->statusLabel() }}</td><td class="p-3">{{ $app->created_at->format('d M Y') }}</td>
        <td class="p-3"><a href="{{ route('admin.professional-applications.show', $app) }}" class="text-blue-600">View</a></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $applications->links() }}</div>@endif
@endsection
