@php
    $brandName = trim(($brand['name'] ?? 'Vyomika Atelier LLP') . ' ' . ($brand['suffix'] ?? ''));
    $phoneRaw = preg_replace('/\s+/', '', $brand['phone'] ?? '');
    $phoneDisplay = $brand['phone'] ?? '';
    $whatsappSource = $social['whatsapp'] ?? $brand['phone'] ?? '';
    $whatsappDigits = preg_replace('/\D/', '', (string) $whatsappSource);
    $whatsappUrl = $whatsappDigits !== '' ? 'https://wa.me/' . ltrim($whatsappDigits, '+') : null;
@endphp

<footer class="am-footer">
    <div class="am-container">
        <div class="am-footer__top am-footer__top--desktop">
            <div class="am-footer__brand">
                <a href="{{ $storefrontLink('home', [], '/') }}" class="am-logo">
                    <span class="am-logo__name">{{ $brandName }}</span>
                </a>
                <p>{{ $footer['newsletter'] ?? '' }}</p>
                <p class="am-footer__brand-address">
                    {{ $brand['address_shop'] ?? 'Pan-India fabrication & delivery' }}<br>
                    {{ $brand['address_office'] ?? 'Mumbai, India' }}
                </p>
            </div>
            <div>
                <h5>Shop</h5>
                <ul>
                    @foreach($footer['shop_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/shop') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Information</h5>
                <ul>
                    @foreach($footer['info_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], [], '/') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Studio</h5>
                <ul>
                    @foreach($footer['service_links'] ?? [] as $link)
                    <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/services') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h5>Legal</h5>
                <ul>
                    @foreach($legalLinks as $link)
                    <li><a href="{{ $storefrontLink($link['route'], [], '/privacy-policy') }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="am-footer__compact" aria-label="Site links">
            <div class="am-footer__compact-brand">
                <a href="{{ $storefrontLink('home', [], '/') }}" class="am-logo">
                    <span class="am-logo__name">{{ $brandName }}</span>
                </a>
                <p class="am-footer__compact-tagline">PVD partitions &amp; bespoke metal fabrication — Mumbai studio, Pan-India delivery.</p>
                <div class="am-footer__compact-cta">
                    @if($whatsappUrl)
                    <a href="{{ $whatsappUrl }}" class="am-btn am-btn--primary am-btn--full" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    @endif
                    <a href="{{ $storefrontLink('contact.index', [], '/contact') }}" class="am-btn am-btn--outline am-btn--full">Contact</a>
                </div>
            </div>

            <div class="am-footer__accordions">
                <div class="am-footer__accordion">
                    <button type="button" class="am-footer__accordion-toggle" data-am-footer-toggle aria-expanded="false" aria-controls="am-footer-panel-shop">
                        Shop
                        <svg class="am-footer__accordion-chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg>
                    </button>
                    <div class="am-footer__accordion-panel" id="am-footer-panel-shop" hidden>
                        <ul>
                            @foreach($footer['shop_links'] ?? [] as $link)
                            <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/shop') }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="am-footer__accordion">
                    <button type="button" class="am-footer__accordion-toggle" data-am-footer-toggle aria-expanded="false" aria-controls="am-footer-panel-info">
                        Information
                        <svg class="am-footer__accordion-chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg>
                    </button>
                    <div class="am-footer__accordion-panel" id="am-footer-panel-info" hidden>
                        <ul>
                            @foreach($footer['info_links'] ?? [] as $link)
                            <li><a href="{{ $storefrontLink($link['route'], [], '/') }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="am-footer__accordion">
                    <button type="button" class="am-footer__accordion-toggle" data-am-footer-toggle aria-expanded="false" aria-controls="am-footer-panel-studio">
                        Studio
                        <svg class="am-footer__accordion-chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg>
                    </button>
                    <div class="am-footer__accordion-panel" id="am-footer-panel-studio" hidden>
                        <ul>
                            @foreach($footer['service_links'] ?? [] as $link)
                            <li><a href="{{ $storefrontLink($link['route'], $link['params'] ?? [], '/services') }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <nav class="am-footer__compact-legal" aria-label="Legal">
                @foreach($legalLinks as $link)
                <a href="{{ $storefrontLink($link['route'], [], '/privacy-policy') }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        </div>

        <div class="am-footer__bottom">
            <span>© {{ date('Y') }} {{ $brandName }}. All rights reserved.</span>
            <div class="am-footer__contact am-footer__contact--desktop">
                <a href="{{ route('vendor-proposal.index') }}">Vendor &amp; Service Proposals</a>
                <a href="{{ route('catalogue.index') }}">Request Catalogue</a>
                <a href="mailto:{{ $brand['email'] ?? '' }}">{{ $brand['email'] ?? '' }}</a>
                @if($phoneDisplay)
                <a href="tel:{{ $phoneRaw }}">{{ $phoneDisplay }}</a>
                @endif
            </div>
        </div>
    </div>
</footer>
