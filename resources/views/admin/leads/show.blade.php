@extends('layouts.admin')

@section('title', 'Lead — ' . $lead->name)

@section('content')
<h1 class="text-2xl font-semibold mb-6">{{ $lead->name }}</h1>

<div class="grid lg:grid-cols-2 gap-8">
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <p class="text-sm text-gray-500 mb-1">{{ $lead->typeLabel() }} · {{ $lead->enquiryTypeLabel() }} · {{ $lead->created_at->format('M d, Y H:i') }}</p>
            @if($lead->enquiryIntentLabel())
                <p class="text-sm text-gray-600 mb-2">Intent: {{ $lead->enquiryIntentLabel() }}</p>
            @endif
            <p class="text-sm mb-2">
                <span class="font-medium">{{ $lead->protectionStatusLabel() }}</span>
                · Score {{ $lead->lead_score }} ({{ $lead->scoreBandLabel() }})
                · {{ $lead->priorityLabel() }} priority
            </p>
            @if(is_array($lead->lead_score_reasons) && $lead->lead_score_reasons !== [])
                <p class="text-xs text-gray-500 mb-3">Score reasons: {{ implode(', ', $lead->lead_score_reasons) }}</p>
            @endif
            @if($lead->duplicate_of_id)
                <p class="text-sm text-amber-700 mb-3">
                    Duplicate of <a href="{{ route('admin.leads.show', $lead->duplicate_of_id) }}" class="underline">lead #{{ $lead->duplicate_of_id }}</a>
                </p>
            @endif
            @if($history['duplicate_count'] > 0)
                <p class="text-sm text-amber-700 mb-3">Original has {{ $history['duplicate_count'] }} duplicate submission(s)</p>
            @endif
            <p class="mb-2">{{ $lead->email }} @if($lead->phone)· {{ $lead->phone }}@endif @if($lead->whatsapp_verified)<span class="text-green-700 text-xs">WhatsApp verified</span>@endif</p>
            @if($lead->first_touch_source)<p class="text-xs text-gray-500 mb-2">Source: {{ $lead->first_touch_source }} @if($lead->utm_campaign)/ {{ $lead->utm_campaign }}@endif · {{ $lead->device_type }}</p>@endif
            @if($lead->subject)<p class="font-medium mb-2">{{ $lead->subject }}</p>@endif
            @if($lead->service_slug)<p class="text-sm text-gray-600 mb-2">Service: {{ $lead->service_slug }}@if($lead->design_slug) / {{ $lead->design_slug }}@endif</p>@endif
            @if($lead->dimensions)<p class="text-sm text-gray-600 mb-2">Dimensions: {{ $lead->dimensions }}</p>@endif
            @if($lead->calculated_price)<p class="text-sm font-medium mb-2">Calculated price: ₹{{ number_format($lead->calculated_price, 0) }}</p>@endif
            @if($lead->budget)<p class="text-sm text-gray-600 mb-2">Budget: {{ $lead->budget }}</p>@endif
            @if($lead->hasAttachment())
            <p><a href="{{ route('admin.leads.attachment', $lead) }}" class="text-blue-600">Download uploaded file</a></p>
            @endif
            <p class="text-gray-700 whitespace-pre-wrap mt-4">{{ $lead->message }}</p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="font-medium mb-4">Contact history</h2>
            @if($history['previous_leads']->isEmpty() && $history['orders']->isEmpty())
                <p class="text-sm text-gray-500">No prior enquiries or orders found.</p>
            @else
                @if($history['customer'])
                    <p class="text-sm mb-2">Registered customer: {{ $history['customer']->name }} ({{ $history['customer']->accountTypeLabel() }})</p>
                @endif
                @foreach($history['previous_leads'] as $prev)
                    <p class="text-sm border-b py-1"><a href="{{ route('admin.leads.show', $prev) }}" class="text-blue-600">#{{ $prev->id }}</a> {{ $prev->enquiryTypeLabel() }} · {{ $prev->created_at->format('M d, Y') }}</p>
                @endforeach
                @foreach($history['orders'] as $order)
                    <p class="text-sm border-b py-1">Order {{ $order->order_number }} · ₹{{ number_format($order->total, 0) }}</p>
                @endforeach
            @endif
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="font-medium mb-4">Activity timeline</h2>
            @forelse($lead->activities as $activity)
                <div class="border-b py-2 text-sm">
                    <p class="font-medium">{{ $activity->typeLabel() }} · {{ $activity->created_at->format('M d, H:i') }}</p>
                    @if($activity->body)<p class="text-gray-600">{{ $activity->body }}</p>@endif
                </div>
            @empty
                <p class="text-sm text-gray-500">No activity yet.</p>
            @endforelse
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="font-medium mb-4">Workflow</h2>
            <form method="POST" action="{{ route('admin.leads.update', $lead) }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="text-sm text-gray-500">Status</label>
                    <select name="status" class="border px-3 py-2 rounded w-full">
                        @foreach($lead->allowedStatuses() as $status)
                            <option value="{{ $status }}" @selected($lead->status === $status)>{{ \App\Support\LeadStatus::label($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Priority</label>
                    <select name="priority" class="border px-3 py-2 rounded w-full">
                        @foreach(['hot','high','medium','low'] as $p)
                            <option value="{{ $p }}" @selected($lead->priority === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Assigned to</label>
                    <select name="assigned_to" class="border px-3 py-2 rounded w-full">
                        <option value="">Unassigned</option>
                        @foreach($assignees as $user)
                            <option value="{{ $user->id }}" @selected($lead->assigned_to == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Next follow-up</label>
                    <input type="datetime-local" name="next_follow_up_at" value="{{ $lead->next_follow_up_at?->format('Y-m-d\TH:i') }}" class="border px-3 py-2 rounded w-full">
                </div>
                <div>
                    <label class="text-sm text-gray-500">Expected order value (₹)</label>
                    <input type="number" step="0.01" name="expected_order_value" value="{{ $lead->expected_order_value }}" class="border px-3 py-2 rounded w-full">
                </div>
                <div>
                    <label class="text-sm text-gray-500">Lost reason</label>
                    <input type="text" name="lost_reason" value="{{ $lead->lost_reason }}" class="border px-3 py-2 rounded w-full">
                </div>
                <div>
                    <label class="text-sm text-gray-500">Admin Notes (legacy)</label>
                    <textarea name="admin_notes" rows="3" class="border px-3 py-2 rounded w-full">{{ old('admin_notes', $lead->admin_notes) }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Add internal note</label>
                    <textarea name="internal_note" rows="2" class="border px-3 py-2 rounded w-full" placeholder="Append-only note…"></textarea>
                </div>
                @if(is_array($lead->internal_notes) && $lead->internal_notes !== [])
                    <div class="text-xs text-gray-500 space-y-1 max-h-32 overflow-y-auto">
                        @foreach(array_reverse($lead->internal_notes) as $note)
                            <p>{{ $note['at'] ?? '' }}: {{ $note['body'] ?? '' }}</p>
                        @endforeach
                    </div>
                @endif
                <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="font-medium mb-4">Actions</h2>
            <div class="flex flex-wrap gap-2 mb-4">
                <form method="POST" action="{{ route('admin.leads.false-positive', $lead) }}">@csrf<button class="text-sm bg-green-700 text-white px-3 py-2 rounded">Mark verified</button></form>
                <form method="POST" action="{{ route('admin.leads.qualified', $lead) }}">@csrf<button class="text-sm bg-indigo-700 text-white px-3 py-2 rounded">Mark qualified</button></form>
                <form method="POST" action="{{ route('admin.leads.restore', $lead) }}">@csrf<button class="text-sm bg-blue-700 text-white px-3 py-2 rounded">Restore</button></form>
                <form method="POST" action="{{ route('admin.leads.mark-vendor', $lead) }}">@csrf<button class="text-sm bg-gray-600 text-white px-3 py-2 rounded">Mark vendor</button></form>
                <form method="POST" action="{{ route('admin.leads.mark-spam', $lead) }}">@csrf<button class="text-sm bg-orange-700 text-white px-3 py-2 rounded">Mark spam</button></form>
                @if($lead->duplicate_of_id)
                <form method="POST" action="{{ route('admin.leads.merge-duplicate', $lead) }}">@csrf<button class="text-sm bg-amber-700 text-white px-3 py-2 rounded">Merge duplicate</button></form>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.leads.block-identity', $lead) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-sm text-gray-500">Block identity</label>
                    <select name="identity_type" class="border px-3 py-2 rounded w-full">
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="ip">IP fingerprint</option>
                        <option value="email_domain">Email domain</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Expires at (optional)</label>
                    <input type="datetime-local" name="expires_at" class="border px-3 py-2 rounded w-full">
                </div>
                <div>
                    <label class="text-sm text-gray-500">Reason (optional)</label>
                    <input type="text" name="reason" class="border px-3 py-2 rounded w-full">
                </div>
                <button class="text-sm bg-red-700 text-white px-3 py-2 rounded">Block identity</button>
            </form>
            <form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" class="mt-4" onsubmit="return confirm('Archive-delete this lead?')">
                @csrf @method('DELETE')
                <button class="text-red-600 text-sm">Delete Lead</button>
            </form>
        </div>
    </div>
</div>
@endsection
