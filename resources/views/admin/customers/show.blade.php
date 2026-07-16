@extends('layouts.admin')
@section('title', $customer->name)
@section('content')
<div class="mb-4"><a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600">← Back</a></div>
<h1 class="text-2xl font-semibold mb-6">{{ $customer->name }}</h1>
<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded shadow text-sm space-y-2">
        <p><strong>Email:</strong> {{ $customer->email }}</p>
        <p><strong>Mobile:</strong> +{{ $customer->mobile_country_code }} {{ $customer->mobile }}</p>
        <p><strong>Account type:</strong> {{ $customer->accountTypeLabel() }}</p>
        <p><strong>Mobile verified:</strong> {{ $customer->hasVerifiedPhone() ? 'Yes' : 'No' }}</p>
        <p><strong>City:</strong> {{ $customer->city ?? '—' }}</p>
        <p><strong>Joined:</strong> {{ $customer->created_at->format('d M Y') }}</p>
    </div>
    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="bg-white p-6 rounded shadow space-y-4">
        @csrf @method('PUT')
        <div><label class="block text-sm mb-1">Account status</label>
            <select name="is_active" class="w-full border px-3 py-2 rounded">
                <option value="1" @selected($customer->is_active)>Enabled</option>
                <option value="0" @selected(! $customer->is_active)>Disabled</option>
            </select>
        </div>
        <button class="bg-gray-900 text-white px-4 py-2 rounded text-sm">Save</button>
    </form>
</div>
<div class="mt-8 grid lg:grid-cols-3 gap-6 text-sm">
    <div class="bg-white p-4 rounded shadow"><h2 class="font-medium mb-2">Recent orders</h2>@forelse($orders as $order)<p class="border-b py-1">#{{ $order->id }} — {{ $order->status }}</p>@empty<p class="text-gray-500">None</p>@endforelse</div>
    <div class="bg-white p-4 rounded shadow"><h2 class="font-medium mb-2">Enquiries</h2>@forelse($leads as $lead)<p class="border-b py-1">{{ $lead->typeLabel() }} — {{ $lead->statusLabel() }}</p>@empty<p class="text-gray-500">None</p>@endforelse</div>
    <div class="bg-white p-4 rounded shadow"><h2 class="font-medium mb-2">Professional applications</h2>@forelse($applications as $app)<p class="border-b py-1">{{ $app->statusLabel() }}</p>@empty<p class="text-gray-500">None</p>@endforelse</div>
</div>
@endsection
