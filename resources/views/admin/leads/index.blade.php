@extends('layouts.admin')

@section('title', 'Leads')

@section('content')
<h1 class="text-2xl font-semibold mb-6">Leads</h1>

<form method="GET" class="flex gap-2 mb-6 flex-wrap text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded">
        <option value="">All statuses</option>
        @foreach(\App\Models\Lead::generalStatuses() as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
        @endforeach
    </select>
    <select name="type" class="border px-3 py-2 rounded">
        <option value="">All types</option>
        @foreach(['custom_order','service_inquiry','order_now','contact','inquiry','professional_application','railing_quotation'] as $type)
            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Filter</button>
</form>

@if($leads->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No leads found.</p>
@else
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
@endif
@endsection
