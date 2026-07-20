@extends('layouts.admin')
@section('title', 'Railing Quotes')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Railing Quote Enquiries</h1>
<form method="GET" class="mb-4 flex gap-2 text-sm flex-wrap">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <select name="status" class="border px-3 py-2 rounded"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
    <select name="protection_status" class="border px-3 py-2 rounded">
        <option value="">All protection filters</option>
        @foreach(\App\Models\Lead::protectionStatuses() as $status)
            <option value="{{ $status }}" @selected(request('protection_status') === $status)>{{ \App\Support\LeadProtectionStatus::label($status) }}</option>
        @endforeach
    </select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($quotes->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No railing quotes yet.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Dimensions</th><th class="p-3">Protection</th><th class="p-3">Risk</th><th class="p-3">Status</th><th class="p-3">Submitted</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($quotes as $quote)<tr class="border-b">
        <td class="p-3">{{ $quote->name }}</td><td class="p-3">{{ $quote->dimensions }}</td>
        <td class="p-3">{{ $quote->protectionStatusLabel() }}</td><td class="p-3">{{ $quote->risk_score }}/100</td>
        <td class="p-3">{{ $quote->statusLabel() }}</td><td class="p-3">{{ $quote->created_at->format('d M Y') }}</td>
        <td class="p-3"><a href="{{ route('admin.railing-quotes.show', $quote) }}" class="text-blue-600">View</a></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $quotes->links() }}</div>@endif
@endsection
