@extends('layouts.admin')
@section('title', 'Railing Quote')
@section('content')
<div class="mb-4"><a href="{{ route('admin.railing-quotes.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $quote->name }}</h1>
<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded shadow text-sm space-y-2">
        <p><strong>Email:</strong> {{ $quote->email }}</p>
        <p><strong>Phone:</strong> {{ $quote->phone }}</p>
        <p><strong>Status:</strong> {{ $quote->statusLabel() }}</p>
        <p><strong>Protection:</strong> {{ $quote->protectionStatusLabel() }} · Risk {{ $quote->risk_score }}/100</p>
        @if(is_array($quote->risk_reasons) && $quote->risk_reasons !== [])
            <p class="text-xs text-gray-500">Reasons: {{ implode(', ', $quote->risk_reasons) }}</p>
        @endif
        @if($quote->enquiryIntentLabel())
            <p><strong>Intent:</strong> {{ $quote->enquiryIntentLabel() }}</p>
        @endif
        <p><a href="{{ route('admin.leads.show', $quote) }}" class="text-blue-600 text-sm">Manage protection actions →</a></p>
        @if(is_array($quote->metadata) && count($quote->metadata))
            <hr class="my-3"><p class="font-medium">Quote details</p>
            <ul class="list-disc pl-4">@foreach($quote->metadata as $key => $value)<li><strong>{{ ucwords(str_replace('_', ' ', $key)) }}:</strong> @if($key === 'drawing_path' && $value)<a href="{{ route('admin.railing-quotes.attachment', $quote->id) }}" class="text-blue-600">Download file</a>@else{{ is_array($value) ? implode(', ', $value) : $value }}@endif</li>@endforeach</ul>
        @endif
        <hr class="my-3"><pre class="whitespace-pre-wrap">{{ $quote->message }}</pre>
    </div>
    <form method="POST" action="{{ route('admin.railing-quotes.update', $quote) }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf @method('PUT')
        <div><label class="block text-sm mb-1">Status</label><select name="status" class="w-full border px-3 py-2 rounded">@foreach($statuses as $status)<option value="{{ $status }}" @selected($quote->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
        <div><label class="block text-sm mb-1">Internal notes</label><textarea name="admin_notes" rows="6" class="w-full border px-3 py-2 rounded">{{ old('admin_notes', $quote->admin_notes) }}</textarea></div>
        <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
    </form>
</div>
@endsection
