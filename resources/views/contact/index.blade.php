@extends('layouts.app')

@section('title', 'Contact — VYOMIKA ATELIER')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="font-serif text-4xl mb-4 text-center">Contact Us</h1>
    <p class="text-brand-700 text-center mb-10">Questions about an order, collaboration, or our collections? We'd love to hear from you.</p>

    <form action="{{ route('contact.store') }}" method="POST" class="space-y-4 bg-white border border-brand-200 p-8">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Subject" required class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">
        <textarea name="message" placeholder="Your message..." required rows="5" class="w-full border border-brand-200 px-4 py-2.5 focus:outline-none focus:border-brand-500">{{ old('message') }}</textarea>
        <button type="submit" class="w-full bg-brand-900 text-white py-3 text-sm uppercase tracking-wider hover:bg-brand-700 transition">Send Message</button>
    </form>
</div>
@endsection
