@extends('layouts.store')

@section('title', 'Dealer & B2B Application')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Trade',
    'title' => 'Dealer & B2B Application',
    'subtitle' => 'Apply for dealership or trade partnership with Vyomika Atelier.',
])

<section class="am-page-body">
    <div class="am-container am-page-body--narrow">
        @if(session('success'))
            <p class="am-alert am-alert--success">{{ session('success') }}</p>
        @endif
        <form action="{{ route('dealer.store') }}" method="POST" class="am-form-stack">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Contact name" required class="am-input">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="am-input">
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="am-input">
            <input type="text" name="company" value="{{ old('company') }}" placeholder="Company / firm name" required class="am-input">
            <input type="text" name="city" value="{{ old('city') }}" placeholder="City" required class="am-input">
            <input type="text" name="gst_number" value="{{ old('gst_number') }}" placeholder="GST / registration (optional)" class="am-input">
            <input type="text" name="years_in_business" value="{{ old('years_in_business') }}" placeholder="Years in business (optional)" class="am-input">
            <textarea name="message" placeholder="Tell us about your business, territories, and product interest…" required rows="5" class="am-input am-textarea">{{ old('message') }}</textarea>
            <x-form-protection-fields form-key="dealer_application" />
            <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Application</button>
        </form>
    </div>
</section>
@endsection
