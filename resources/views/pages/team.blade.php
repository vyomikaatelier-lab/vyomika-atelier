@extends('layouts.store')

@section('title', 'Our Team — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'The Studio',
    'title' => 'Our Team',
    'subtitle' => 'Fabricators, designers, and project leads united by precision metalwork.',
])

<section class="am-page-body">
    <div class="am-container">
        <div class="am-grid-3">
            @foreach(\App\Support\SiteContent::team() as $member)
            <div class="am-card">
                <div class="am-card__thumb" style="aspect-ratio:1">
                    <img src="{{ $member['image'] }}" alt="{{ $member['name'] }}">
                </div>
                <div class="am-card__body">
                    <p class="am-card__title">{{ $member['name'] }}</p>
                    <p class="am-card__text">{{ $member['role'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
        <div style="text-align:center;margin-top:4rem">
            <button type="button" class="am-btn am-btn--outline" data-open-contact-studio data-contact-context="Work with our team">Work With Us →</button>
        </div>
    </div>
</section>
@endsection
