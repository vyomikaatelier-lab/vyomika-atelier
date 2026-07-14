@extends('layouts.admin')

@section('title', 'Leads')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Leads</h1>

<form method="GET" class="flex gap-4 mb-6">
    <select name="status" class="border px-3 py-2 rounded text-sm">
        <option value="">All statuses</option>
        @foreach(['new','contacted','quoted','converted','closed'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <select name="type" class="border px-3 py-2 rounded text-sm">
        <option value="">All types</option>
        @foreach(['custom_order','contact','inquiry'] as $type)
            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Filter</button>
</form>

<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Type</th><th class="p-3">Status</th><th class="p-3">Date</th><th class="p-3"></th></tr></thead>
    <tbody>
        @foreach($leads as $lead)
        <tr class="border-b">
            <td class="p-3">{{ $lead->name }}<br><span class="text-gray-500">{{ $lead->email }}</span></td>
            <td class="p-3">{{ $lead->typeLabel() }}</td>
            <td class="p-3">{{ $lead->statusLabel() }}</td>
            <td class="p-3">{{ $lead->created_at->format('M d, Y') }}</td>
            <td class="p-3"><a href="{{ route('admin.leads.show', $lead) }}" class="text-blue-600">View</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $leads->links() }}</div>
@endsection
