@extends('layouts.store')

@section('title', 'Page not found — Vyomika Atelier')

@section('content')
<section class="section">
    <div class="am-container">
        <h1>Page not found</h1>
        <p>The page you requested is not available.</p>
        <p><a href="{{ route('home') }}">Return to homepage</a></p>
    </div>
</section>
@endsection
