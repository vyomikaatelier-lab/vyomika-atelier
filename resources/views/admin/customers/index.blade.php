@extends('layouts.admin')
@section('title', 'Customers')
@section('content')
<h1 class="text-2xl font-semibold mb-6">Customers</h1>
<form method="GET" class="mb-4 flex gap-2 text-sm flex-wrap">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="border px-3 py-2 rounded">
    <select name="account_type" class="border px-3 py-2 rounded"><option value="">All types</option>@foreach($accountTypes as $key => $label)<option value="{{ $key }}" @selected(request('account_type') === $key)>{{ $label }}</option>@endforeach</select>
    <select name="verified" class="border px-3 py-2 rounded"><option value="">Verification</option><option value="yes" @selected(request('verified')==='yes')>Verified</option><option value="no" @selected(request('verified')==='no')>Not verified</option></select>
    <select name="status" class="border px-3 py-2 rounded"><option value="">Account</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Disabled</option></select>
    <button class="border px-3 py-2 rounded">Filter</button>
</form>
@if($customers->isEmpty())<p class="text-gray-500 bg-white p-6 rounded shadow">No registered customers yet.</p>@else
<table class="w-full bg-white rounded-lg shadow text-sm">
    <thead class="border-b"><tr class="text-left"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Type</th><th class="p-3">Mobile verified</th><th class="p-3">Account</th><th class="p-3"></th></tr></thead>
    <tbody>@foreach($customers as $customer)<tr class="border-b">
        <td class="p-3">{{ $customer->name }}</td><td class="p-3">{{ $customer->email }}</td><td class="p-3">{{ $customer->accountTypeLabel() }}</td>
        <td class="p-3">{{ $customer->hasVerifiedPhone() ? 'Yes' : 'No' }}</td><td class="p-3">{{ $customer->is_active ? 'Active' : 'Disabled' }}</td>
        <td class="p-3"><a href="{{ route('admin.customers.show', $customer) }}" class="text-blue-600">View</a></td>
    </tr>@endforeach</tbody>
</table>
<div class="mt-4">{{ $customers->links() }}</div>@endif
@endsection
