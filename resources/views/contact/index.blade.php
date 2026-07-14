@extends('layouts.app')

@section('title', 'Contact — VYOMIKA ATELIER')

@section('content')
<div class="va-page-hero">
    <p class="va-label mb-3">Get in Touch</p>
    <h1 class="font-serif text-5xl text-brand-900">Contact</h1>
</div>

<div class="max-w-xl mx-auto px-5 py-16">
    <form action="{{ route('contact.store') }}" method="POST" class="space-y-5">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="va-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="va-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)" class="va-input">
        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Subject" required class="va-input">
        <textarea name="message" placeholder="Your message…" required rows="5" class="va-input">{{ old('message') }}</textarea>
        <button type="submit" class="va-btn-primary w-full text-center">Send Message</button>
    </form>
    <div class="mt-16 text-center text-brand-500 text-sm">
        <p class="va-label mb-3">Direct</p>
        <p>hello@vyomikaatelier.com</p>
    </div>
</div>
@endsection
