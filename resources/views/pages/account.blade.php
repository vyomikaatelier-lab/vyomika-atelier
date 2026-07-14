@extends('layouts.app')

@section('title', 'Account — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-eyebrow mb-3">Your Studio</p>
    <h1 class="va-display text-4xl md:text-5xl">Account</h1>
</div>

<div class="max-w-lg mx-auto px-5 py-20 space-y-6">
    <a href="{{ route('leads.create') }}" class="block border border-brand-200 p-6 hover:border-brand-500 transition group">
        <p class="va-eyebrow mb-2">Bespoke</p>
        <h2 class="font-serif text-xl group-hover:text-brand-500 transition">Custom Order Request</h2>
        <p class="text-sm text-brand-400 mt-2 font-light">Submit dimensions and requirements for a tailored quote.</p>
    </a>
    <a href="{{ route('cart.index') }}" class="block border border-brand-200 p-6 hover:border-brand-500 transition group">
        <p class="va-eyebrow mb-2">Collection</p>
        <h2 class="font-serif text-xl group-hover:text-brand-500 transition">View Cart</h2>
        <p class="text-sm text-brand-400 mt-2 font-light">{{ app(\App\Services\CartService::class)->count() }} item(s) in your bag.</p>
    </a>
    <a href="{{ route('contact.index') }}" class="block border border-brand-200 p-6 hover:border-brand-500 transition group">
        <p class="va-eyebrow mb-2">Support</p>
        <h2 class="font-serif text-xl group-hover:text-brand-500 transition">Contact Studio</h2>
        <p class="text-sm text-brand-400 mt-2 font-light">Questions about an existing order or project.</p>
    </a>
    <a href="{{ route('admin.login') }}" class="block border border-brand-200 p-6 hover:border-brand-500 transition group opacity-80">
        <p class="va-eyebrow mb-2">Team</p>
        <h2 class="font-serif text-xl group-hover:text-brand-500 transition">Admin Login</h2>
        <p class="text-sm text-brand-400 mt-2 font-light">For VYOMIKA ATELIER staff only.</p>
    </a>
</div>
@endsection
