@extends('layouts.store')

@section('title', 'Contact — Vyomika Atelier')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Get in Touch',
    'title' => 'Contact',
    'subtitle' => 'Tell us about your project — partitions, façades, doors, or bespoke metalwork.',
])

<section class="am-page-body">
    <div class="am-container am-page-body--narrow">
        <form action="{{ route('contact.store') }}" method="POST" class="am-form-stack">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="am-input">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="am-input">
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)" class="am-input">
            <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Subject" required class="am-input">
            <textarea name="message" placeholder="Your message…" required rows="5" class="am-input am-textarea">{{ old('message') }}</textarea>
            <x-form-protection-fields form-key="contact" />
            <button type="submit" class="am-btn am-btn--primary am-btn--full">Send Message</button>
        </form>

        @include('partials.am-business-details')

        <div style="margin-top:2rem;text-align:center">
            <p class="am-card__label">Direct</p>
            <a href="mailto:{{ config('site.brand.email') }}" style="color:var(--am-gold-dark)">{{ config('site.brand.email') }}</a>
        </div>
    </div>
</section>
@endsection
