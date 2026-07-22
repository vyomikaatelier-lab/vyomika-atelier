@extends('layouts.store')

@section('title', 'Custom Order — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Made to Order',
    'title' => 'Custom Order',
    'subtitle' => 'Need a specific size, material, or design? Tell us what you need and we will get back to you with a quote.',
])

<section class="am-page-body">
    <div class="am-container am-page-body--narrow">
        <form action="{{ route('leads.store') }}" method="POST" class="am-form-stack">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="am-input">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="am-input">
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="am-input">
            <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Product type (e.g. Glass coffee table)" class="am-input">
            <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="am-input">
            <select name="preferred_contact" class="am-input">
                <option value="">Preferred contact method</option>
                <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
                <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
                <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
            </select>
            <textarea name="message" placeholder="Describe your requirements — dimensions, material, colour, room type…" required rows="6" class="am-input am-textarea">{{ old('message') }}</textarea>
            <x-form-protection-fields form-key="custom_order" />
            <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Request</button>
        </form>
    </div>
</section>
@endsection
