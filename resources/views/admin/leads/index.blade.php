@extends('layouts.admin')

@section('title', 'Leads')

@section('content')
<h1 class="text-2xl font-semibold mb-4">Leads</h1>

<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 mb-6 text-xs">
    @foreach($stats as $key => $count)
        <div class="bg-white p-3 rounded shadow">
            <p class="text-gray-500">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
            <p class="text-lg font-semibold">{{ $count }}</p>
        </div>
    @endforeach
</div>

<form method="GET" class="flex gap-2 mb-6 flex-wrap text-sm">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email…" class="border px-3 py-2 rounded">
    <select name="enquiry_type" class="border px-3 py-2 rounded">
        <option value="">All enquiry types</option>
        @foreach(config('lead_qualification.enquiry_types', []) as $value => $label)
            <option value="{{ $value }}" @selected(request('enquiry_type') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="protection_status" class="border px-3 py-2 rounded">
        <option value="">All protection</option>
        @foreach(\App\Models\Lead::protectionStatuses() as $status)
            <option value="{{ $status }}" @selected(request('protection_status') === $status)>{{ \App\Support\LeadProtectionStatus::label($status) }}</option>
        @endforeach
    </select>
    <select name="status" class="border px-3 py-2 rounded">
        <option value="">All workflow statuses</option>
        @foreach(\App\Models\Lead::workflowStatuses() as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\LeadStatus::label($status) }}</option>
        @endforeach
    </select>
    <select name="priority" class="border px-3 py-2 rounded">
        <option value="">All priorities</option>
        @foreach(['hot','high','medium','low'] as $p)
            <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
        @endforeach
    </select>
    <select name="assigned_to" class="border px-3 py-2 rounded">
        <option value="">All assignees</option>
        @foreach($assignees as $user)
            <option value="{{ $user->id }}" @selected((string) request('assigned_to') === (string) $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>
    <input type="number" name="score_min" value="{{ request('score_min') }}" placeholder="Min score" class="border px-3 py-2 rounded w-24">
    <label class="flex items-center gap-1"><input type="checkbox" name="sales_queue" value="1" @checked(request('sales_queue'))> Sales only</label>
    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded">Filter</button>
</form>

@if($leads->isEmpty())
    <p class="text-gray-500 bg-white p-6 rounded shadow">No leads found.</p>
@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left">
        <th class="p-3">Name</th><th class="p-3">Enquiry</th><th class="p-3">Score</th><th class="p-3">Protection</th><th class="p-3">Status</th><th class="p-3">Priority</th><th class="p-3">Date</th><th class="p-3"></th>
    </tr></thead>
    <tbody>
        @foreach($leads as $lead)
        <tr class="border-b">
            <td class="p-3">{{ $lead->name }}<br><span class="text-gray-500">{{ $lead->email }}</span></td>
            <td class="p-3">{{ $lead->enquiryTypeLabel() }}</td>
            <td class="p-3">{{ $lead->lead_score }}<br><span class="text-xs text-gray-500">{{ $lead->scoreBandLabel() }}</span></td>
            <td class="p-3">{{ $lead->protectionStatusLabel() }}</td>
            <td class="p-3">{{ $lead->statusLabel() }}</td>
            <td class="p-3">{{ $lead->priorityLabel() }}</td>
            <td class="p-3">{{ $lead->created_at->format('M d, Y') }}</td>
            <td class="p-3"><a href="{{ route('admin.leads.show', $lead) }}" class="text-blue-600">View</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $leads->links() }}</div>
@endif
@endsection
