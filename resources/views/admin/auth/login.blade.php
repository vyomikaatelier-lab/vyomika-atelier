@extends('layouts.admin')

@section('title', 'Login')

@section('content')
<div class="max-w-sm mx-auto mt-20">
    <h1 class="text-2xl font-semibold mb-6">Admin Login</h1>
    @if($errors->any())
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="w-full border px-3 py-2 rounded">
        <input type="password" name="password" placeholder="Password" required class="w-full border px-3 py-2 rounded">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit" class="w-full bg-gray-900 text-white py-2 rounded hover:bg-gray-800">Login</button>
    </form>
</div>
@endsection
