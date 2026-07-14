@extends('layouts.app')

@section('title', 'Custom Order — VYOMIKA ATELIER')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="font-serif text-4xl mb-4 text-center">Request a Custom Piece</h1>
    <p class="text-brand-700 text-center mb-10">Tell us about your vision — fabric, occasion, measurements, or inspiration. We'll reach out to discuss your bespoke commission.</p>

    <form action="{{ route('leads.store') }}" method="POST" class="space-y-4 bg-white border border-brand-200 p-8">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Occasion or piece type (e.g. Wedding lehenga)" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <select name="preferred_contact" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
            <option value="">Preferred contact method</option>
            <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
            <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Describe your vision — style, fabric, timeline, measurements..." required rows="6" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">{{ old('message') }}</textarea>
        <button type="submit" class="w-full bg-brand-900 text-white py-3 text-sm uppercase tracking-wider hover:bg-brand-700 transition">Submit Request</button>
    </form>
</div>
@endsection
