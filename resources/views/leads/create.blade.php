@extends('layouts.app')

@section('title', 'Custom Order — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Made to Order</p>
    <h1 class="font-serif text-5xl text-brand-900">Custom Furniture</h1>
    <p class="text-brand-500 mt-4 max-w-lg mx-auto">Need a specific size, material, or design? Tell us what you need and we'll get back to you with a quote.</p>
</div>

<div class="max-w-xl mx-auto px-5 py-16">
    <form action="{{ route('leads.store') }}" method="POST" class="space-y-5">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="va-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="va-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="va-input">
        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Product type (e.g. Glass coffee table, Corner table)" class="va-input">
        <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="va-input">
        <select name="preferred_contact" class="va-input">
            <option value="">Preferred contact method</option>
            <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
            <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Describe your requirements — dimensions, material, colour, room type…" required rows="6" class="va-input">{{ old('message') }}</textarea>
        <button type="submit" class="va-btn-primary w-full text-center">Submit Request</button>
    </form>
</div>
@endsection
