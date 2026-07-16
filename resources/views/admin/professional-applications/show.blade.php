@extends('layouts.admin')
@section('title', 'Application')
@section('content')
<div class="mb-4"><a href="{{ route('admin.professional-applications.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $application->name }}</h1>
<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded shadow text-sm space-y-2">
        <p><strong>Email:</strong> {{ $application->email }}</p>
        <p><strong>Phone:</strong> {{ $application->phone }}</p>
        <p><strong>Status:</strong> {{ $application->statusLabel() }}</p>
        <p><strong>Submitted:</strong> {{ $application->created_at->format('d M Y H:i') }}</p>
        @if(is_array($application->metadata) && count($application->metadata))
            <hr class="my-3">
            <p class="font-medium">Structured details</p>
            <ul class="list-disc pl-4">@foreach($application->metadata as $key => $value)<li><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> {{ is_array($value) ? implode(', ', $value) : $value }}</li>@endforeach</ul>
        @endif
        @if($application->hasAttachment())
        <p><a href="{{ route('admin.professional-applications.attachment', $application->id) }}" class="text-blue-600">Download uploaded file</a></p>
        @endif
        <hr class="my-3">
        <p class="font-medium">Message</p>
        <pre class="whitespace-pre-wrap text-gray-700">{{ $application->message }}</pre>
    </div>
    <form method="POST" action="{{ route('admin.professional-applications.update', $application) }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf @method('PUT')
        <div><label class="block text-sm mb-1">Status</label><select name="status" class="w-full border px-3 py-2 rounded">@foreach($statuses as $status)<option value="{{ $status }}" @selected($application->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Internal notes</label><textarea name="admin_notes" rows="6" class="w-full border px-3 py-2 rounded">{{ old('admin_notes', $application->admin_notes) }}</textarea></div>
        <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
    </form>
</div>
@endsection
