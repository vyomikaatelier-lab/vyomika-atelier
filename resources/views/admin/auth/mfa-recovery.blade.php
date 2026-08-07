@extends('layouts.admin')

@section('title', 'Recovery codes')

@section('content')
<div class="max-w-lg">
    <h1 class="text-2xl font-semibold mb-4">MFA recovery codes</h1>
    <p class="text-sm text-gray-600 mb-4">Store these codes offline. Each code works once. They will not be shown again.</p>
    @if(count($codes))
    <ul class="bg-white rounded shadow p-4 font-mono text-sm space-y-1 mb-4">
        @foreach($codes as $code)
        <li>{{ $code }}</li>
        @endforeach
    </ul>
    @else
    <p class="text-sm text-gray-500 mb-4">No new codes in this session. Regenerate from MFA settings if needed.</p>
    @endif
    <a href="{{ route('admin.dashboard') }}" class="bg-gray-900 text-white px-4 py-2 rounded text-sm inline-block">Continue to dashboard</a>
    <a href="{{ route('admin.mfa.manage') }}" class="ml-2 text-sm text-blue-600">MFA settings</a>
</div>
@endsection
