@extends('layouts.admin')

@section('title', 'Lead — ' . $lead->name)

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $lead->name }}</h1>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-sm text-gray-500 mb-1">{{ $lead->typeLabel() }} · {{ $lead->created_at->format('M d, Y H:i') }}</p>
        <p class="mb-2">{{ $lead->email }} @if($lead->phone)· {{ $lead->phone }}@endif</p>
        @if($lead->subject)<p class="font-medium mb-2">{{ $lead->subject }}</p>@endif
        @if($lead->service_slug)<p class="text-sm text-gray-600 mb-2">Service: {{ $lead->service_slug }}@if($lead->design_slug) / {{ $lead->design_slug }}@endif</p>@endif
        @if($lead->dimensions)<p class="text-sm text-gray-600 mb-2">Dimensions: {{ $lead->dimensions }} ({{ $lead->unit_type }})</p>@endif
        @if($lead->calculated_price)<p class="text-sm font-medium text-gray-800 mb-2">Calculated price: ₹{{ number_format($lead->calculated_price, 0) }}</p>@endif
        @if($lead->budget)<p class="text-sm text-gray-600 mb-2">Budget: {{ $lead->budget }}</p>@endif
        @if($lead->hasAttachment())
        <p><a href="{{ route('admin.leads.attachment', $lead) }}" class="text-blue-600">Download uploaded file</a></p>
        @endif
        <p class="text-gray-700 whitespace-pre-wrap">{{ $lead->message }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-sm text-gray-500">Status</label>
                <select name="status" class="border px-3 py-2 rounded w-full">
                    @foreach($lead->allowedStatuses() as $status)
                        <option value="{{ $status }}" @selected($lead->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-500">Admin Notes</label>
                <textarea name="admin_notes" rows="4" class="border px-3 py-2 rounded w-full">{{ old('admin_notes', $lead->admin_notes) }}</textarea>
            </div>
            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
        </form>
        <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" class="mt-4" onsubmit="return confirm('Delete this lead?')">
            @csrf @method('DELETE')
            <button class="text-red-600 text-sm">Delete Lead</button>
        </form>
    </div>
</div>
@endsection
