@extends('layouts.admin')

@section('title', 'Lead — ' . $lead->name)

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $lead->name }}</h1>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-sm text-gray-500 mb-1">{{ $lead->typeLabel() }} · {{ $lead->created_at->format('M d, Y H:i') }}</p>
        <p class="mb-2">{{ $lead->email }} @if($lead->phone)· {{ $lead->phone }}@endif</p>
        @if($lead->subject)<p class="font-medium mb-2">{{ $lead->subject }}</p>@endif
        @if($lead->budget)<p class="text-sm text-gray-600 mb-2">Budget: {{ $lead->budget }}</p>@endif
        <p class="text-gray-700 whitespace-pre-wrap">{{ $lead->message }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-sm text-gray-500">Status</label>
                <select name="status" class="border px-3 py-2 rounded w-full">
                    @foreach(['new','contacted','quoted','converted','closed'] as $status)
                        <option value="{{ $status }}" @selected($lead->status === $status)>{{ ucfirst($status) }}</option>
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
