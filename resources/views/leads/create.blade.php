@extends('layouts.app')

@section('title', 'Bespoke Commission — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Made for You</p>
    <h1 class="font-serif text-5xl text-brand-900">Commission a Piece</h1>
    <p class="text-brand-500 mt-4 max-w-lg mx-auto">Share your vision — fabric, occasion, style. We'll reach out to begin the journey.</p>
</div>

<div class="max-w-xl mx-auto px-5 py-16">
    <form action="{{ route('leads.store') }}" method="POST" class="space-y-5">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="va-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="va-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="va-input">
        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Occasion or piece type (e.g. Wedding lehenga)" class="va-input">
        <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="va-input">
        <select name="preferred_contact" class="va-input">
            <option value="">Preferred contact method</option>
            <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
            <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Describe your vision — style, fabric, timeline, measurements…" required rows="6" class="va-input">{{ old('message') }}</textarea>
        <button type="submit" class="va-btn-primary w-full text-center">Submit Request</button>
    </form>
</div>
@endsection
