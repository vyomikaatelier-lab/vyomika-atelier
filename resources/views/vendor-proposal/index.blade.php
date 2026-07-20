@extends('layouts.store')

@section('title', 'Vendor & Service Proposals')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Partnerships',
    'title' => 'Vendor & Service Proposals',
    'subtitle' => 'For agencies, suppliers, and service providers — not customer project enquiries.',
])

<section class="am-page-body">
    <div class="am-container am-page-body--narrow">
        @if(session('success'))
            <p class="am-alert am-alert--success">{{ session('success') }}</p>
        @endif
        <form action="{{ route('vendor-proposal.store') }}" method="POST" class="am-form-stack">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required class="am-input">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Business email" required class="am-input">
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone (optional)" class="am-input">
            <input type="text" name="company" value="{{ old('company') }}" placeholder="Company name" required class="am-input">
            <textarea name="message" placeholder="Brief proposal (services, portfolio, pricing model)…" required rows="5" class="am-input am-textarea">{{ old('message') }}</textarea>
            <x-form-protection-fields form-key="vendor_proposal" :show-intent="false" />
            <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Proposal</button>
        </form>
        <p class="text-sm text-gray-600 mt-6 text-center">
            Looking for a project quote? <a href="{{ route('contact.index') }}">Contact our studio</a> instead.
        </p>
    </div>
</section>
@endsection
