@php
    use App\Support\SiteContent;
    use App\Support\StorefrontUrl;

    $brand = SiteContent::brand();
    $homeUrl = StorefrontUrl::to('home', [], '/');
@endphp

<section {{ $attributes->merge(['class' => 'am-page-body am-page-body--account-auth']) }}>
    <div class="am-account-auth-layout">
        <a href="{{ $homeUrl }}" class="am-account-auth__logo am-logo">
            <span class="am-logo__name">{{ $brand['name'] ?? config('site.brand.name', 'Vyomika Atelier') }}</span>
            <span class="am-logo__tag">{{ $brand['tagline'] ?? 'PVD Partitions & Metal Furniture' }}</span>
        </a>

        <div class="am-account-auth-card-wrap">
            {{ $slot }}
        </div>

        <footer class="am-account-auth__legal">
            <p>By continuing, you agree to Vyomika Atelier&rsquo;s <a href="{{ route('legal.terms') }}">Terms</a> and <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
        </footer>
    </div>
</section>
