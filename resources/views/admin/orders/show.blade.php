@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
<h1 class="text-2xl font-semibold mb-6">Order {{ $order->order_number }}</h1>

@if($order->needsPaymentReview())
<div class="mb-6 rounded-lg border border-amber-400 bg-amber-50 p-4" role="status">
    <p class="font-semibold text-amber-950">Reconciliation required</p>
    <p class="mt-1 text-sm text-amber-950">Payment received — order under review. Do not take another payment for this order.</p>
    <p class="mt-2 text-sm">Reason: {{ $order->reconciliationReasonLabel() }}</p>
    @if($order->payment_id)
        <p class="mt-1 text-sm">Payment reference: {{ $order->payment_id }}</p>
    @endif
    @if($order->razorpay_order_id)
        <p class="mt-1 text-sm">Gateway order reference: {{ $order->razorpay_order_id }}</p>
    @endif
    @foreach(($order->reconciliation_meta['extra_payment_ids'] ?? []) as $extraPaymentId)
        <p class="mt-1 text-sm">Additional payment reference: {{ $extraPaymentId }}</p>
    @endforeach
    @foreach(($order->reconciliation_meta['conflicting_payment_ids'] ?? []) as $conflictPaymentId)
        <p class="mt-1 text-sm">Conflicting payment reference: {{ $conflictPaymentId }}</p>
    @endforeach
</div>
@endif

<div class="grid lg:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="font-medium mb-4">Customer</h2>
        <p>{{ $order->customer_name }}</p>
        <p class="text-sm text-gray-600">{{ $order->customer_email }} · {{ $order->customer_phone }}</p>
        <p class="text-sm mt-2">{{ $order->shipping_address }}, {{ $order->city }} {{ $order->pincode }}</p>
        @if($order->notes)<p class="text-sm mt-2 text-gray-600">Customer notes: {{ $order->notes }}</p>@endif
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="font-medium mb-4">Update Order</h2>
        <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="space-y-3">
            @csrf @method('PUT')
            @if($order->needsPaymentReview())
            <p class="text-sm">Recorded status: {{ ucfirst((string) $order->status) }}</p>
            <p class="text-sm">Customer-facing status: {{ $order->statusLabel() }}</p>
            <p class="text-sm text-gray-600">Status changes are locked while payment reconciliation is open.</p>
            @else
            <div>
                <label class="block text-sm mb-1">Status</label>
                <select name="status" class="border px-3 py-2 rounded w-full">
                    @foreach($order->adminStatusOptions() as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                @if($order->hasCapturedPayment())
                <p class="text-sm text-gray-600 mt-2">Cancelling a paid order requires a refund.</p>
                @endif
            </div>
            @endif
            <div>
                <label class="block text-sm mb-1">Admin notes</label>
                <textarea name="admin_notes" rows="4" class="border px-3 py-2 rounded w-full text-sm">{{ old('admin_notes', $order->admin_notes) }}</textarea>
            </div>
            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Update</button>
        </form>
        <p class="mt-4 text-sm">Payment: {{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</p>
        <p class="text-lg font-semibold mt-2">Total: ₹{{ number_format($order->total, 0) }}</p>
        @if($order->customerRefundSummary())
        <p class="text-sm mt-2">{{ $order->customerRefundSummary() }}</p>
        @endif
    </div>
</div>

@if(auth()->user()->hasAdminPermission(\App\Support\AdminRole::ORDERS_REFUND))
<div class="bg-white p-6 rounded-lg shadow mt-8">
    <h2 class="font-medium mb-4">Refunds</h2>
    @forelse($order->refunds as $refund)
        <div class="border-b py-3 text-sm">
            <p>₹{{ \App\Services\RefundMoney::formatRupees((int) $refund->amount_paise) }} · {{ str_replace('_', ' ', $refund->status) }} · {{ str_replace('_', ' ', $refund->reason_code) }}</p>
            @if($refund->gateway_refund_id)
            <p class="text-gray-600">Gateway refund reference: {{ $refund->gateway_refund_id }}</p>
            @endif
            @if($refund->receipt)
            <p class="text-gray-600">Receipt: {{ $refund->receipt }}</p>
            @endif
            @if($refund->internal_note)
            <p class="mt-1">Internal note: {{ $refund->internal_note }}</p>
            @endif
            @if(in_array($refund->status, [\App\Models\OrderRefund::STATUS_RESERVED, \App\Models\OrderRefund::STATUS_SUBMIT_UNCERTAIN], true))
            @if($refund->status === \App\Models\OrderRefund::STATUS_SUBMIT_UNCERTAIN)
            <p class="mt-2">Outcome not yet confirmed. Retrying uses the same idempotency key and cannot change the amount.</p>
            @endif
            <form method="POST" action="{{ route('admin.orders.refunds.retry', [$order, $refund]) }}" class="mt-2 space-y-2">
                @csrf
                <label class="block text-sm" for="retry_password_{{ $refund->id }}">Current password</label>
                <input id="retry_password_{{ $refund->id }}" type="password" name="current_password" required autocomplete="current-password" class="border px-3 py-2 rounded w-full max-w-sm">
                <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Retry the same refund</button>
            </form>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-600">No refunds have been recorded.</p>
    @endforelse

    @if($order->canOfferRefund() && $idempotencyKey)
    <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}" class="space-y-3 mt-6">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <div>
            <label class="block text-sm mb-1" for="refund_kind">Refund</label>
            <select id="refund_kind" name="kind" class="border px-3 py-2 rounded w-full max-w-sm">
                <option value="full" @selected(old('kind', 'full') === 'full')>Full refund and cancel</option>
                <option value="partial" @selected(old('kind') === 'partial')>Partial refund</option>
            </select>
        </div>
        <div>
            <label class="block text-sm mb-1" for="refund_reason">Reason</label>
            <select id="refund_reason" name="reason_code" class="border px-3 py-2 rounded w-full max-w-sm">
                @foreach(\App\Models\OrderRefund::REASONS as $reason)
                <option value="{{ $reason }}" @selected(old('reason_code') === $reason)>{{ str_replace('_', ' ', $reason) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <p class="text-sm mb-1">Partial quantities</p>
            @foreach($order->items as $item)
            <label class="block text-sm mb-2">{{ $item->product_name }} ({{ $item->quantity }} ordered)
                <input type="number" name="lines[{{ $item->id }}]" min="0" max="{{ $item->quantity }}" value="{{ old('lines.'.$item->id, 0) }}" class="border px-3 py-2 rounded w-28 ml-2">
            </label>
            @endforeach
            <label class="block text-sm mt-2">
                <input type="checkbox" name="include_shipping" value="1" @checked(old('include_shipping'))>
                Refund remaining shipping
            </label>
        </div>
        <div>
            <label class="block text-sm mb-1" for="refund_note">Internal note</label>
            <textarea id="refund_note" name="internal_note" rows="3" class="border px-3 py-2 rounded w-full text-sm">{{ old('internal_note') }}</textarea>
        </div>
        <div>
            <label class="block text-sm mb-1" for="order_number_confirmation">Type the order number to confirm a full refund</label>
            <input id="order_number_confirmation" name="order_number_confirmation" value="{{ old('order_number_confirmation') }}" class="border px-3 py-2 rounded w-full max-w-sm" autocomplete="off">
        </div>
        <div>
            <label class="block text-sm mb-1" for="refund_password">Current password</label>
            <input id="refund_password" type="password" name="current_password" required autocomplete="current-password" class="border px-3 py-2 rounded w-full max-w-sm">
        </div>
        <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Submit refund</button>
    </form>
    @endif
</div>
@endif

<div class="bg-white p-6 rounded-lg shadow mt-8">
    <h2 class="font-medium mb-4">Items</h2>
    @foreach($order->items as $item)
        <div class="flex justify-between py-2 border-b text-sm gap-4">
            <div>
                <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                @if($item->finish_name)
                    <span class="block text-xs text-gray-500">Finish: {{ $item->finish_name }}</span>
                @endif
                @if($item->size_label)
                    <span class="block text-xs text-gray-500">Size: {{ $item->size_label }}</span>
                @endif
            </div>
            <span>₹{{ number_format($item->total, 0) }}</span>
        </div>
    @endforeach
</div>
@endsection
