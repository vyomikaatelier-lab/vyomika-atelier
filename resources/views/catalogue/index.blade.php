@extends('layouts.store')

@section('title', 'Request Catalogue')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Resources',
    'title' => 'Request Catalogue',
    'subtitle' => 'Download our product catalogue for architects, designers, and project teams.',
])

<section class="am-page-body">
    <div class="am-container am-page-body--narrow">
        @if(session('success'))
            <p class="am-alert am-alert--success">{{ session('success') }}</p>
        @endif
        @if(session('catalogue_download_url'))
            <p class="mb-4"><a href="{{ session('catalogue_download_url') }}" class="am-btn am-btn--primary">Download Catalogue (expires in 72h)</a></p>
        @endif
        <form action="{{ route('catalogue.store') }}" method="POST" class="am-form-stack">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required class="am-input">
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="am-input">
            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="am-input">
            <input type="text" name="profession" value="{{ old('profession') }}" placeholder="Profession (e.g. Architect, Designer)" required class="am-input">
            <input type="text" name="city" value="{{ old('city') }}" placeholder="City" required class="am-input">
            <textarea name="message" placeholder="Optional notes" rows="3" class="am-input am-textarea">{{ old('message') }}</textarea>
            <x-form-protection-fields form-key="catalogue_request" :show-intent="false" />
            <button type="submit" class="am-btn am-btn--primary am-btn--full">Request Catalogue</button>
        </form>
    </div>
</section>
@endsection
