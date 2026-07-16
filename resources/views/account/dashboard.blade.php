@extends('layouts.store')

@section('title', 'My Account — Vyomika Atelier LLP')

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Your Studio',
    'title' => 'My Account',
    'subtitle' => $user->name . ' · ' . $user->accountTypeLabel(),
])

<section class="am-page-body am-account-dashboard">
    <div class="am-container">
        @include('partials.am-account-alerts')

        <div class="am-account-dashboard__grid">
            <section class="am-card am-account-panel" id="profile">
                <div class="am-card__body">
                    <p class="am-card__label">Profile</p>
                    <h2 class="am-card__title">Profile Details</h2>
                    <form action="{{ route('account.profile.update') }}" method="POST" class="am-form-stack am-form-stack--compact">
                        @csrf
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="am-input" required>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="am-input" required>
                        <p class="am-account-meta">Mobile: {{ $user->mobile_country_code }} {{ $user->mobile }} (verified)</p>
                        <input type="tel" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}" placeholder="WhatsApp" class="am-input">
                        <input type="text" name="city" value="{{ old('city', $user->city) }}" placeholder="City" class="am-input">
                        <button type="submit" class="am-btn am-btn--outline am-btn--sm">Save Profile</button>
                    </form>
                </div>
            </section>

            <section class="am-card am-account-panel am-address-form-card" id="addresses">
                <div class="am-card__body">
                    <h2 class="am-address-form__title">My Address</h2>

                    @forelse($addresses as $address)
                    <article class="am-account-list-item">
                        <div>
                            <strong>{{ $address->label }}</strong>
                            @if($address->is_default)<span class="am-account-badge">Default</span>@endif
                            <p>{{ $address->name }} · {{ $address->phone }}</p>
                            <p class="am-account-meta">{{ $address->formatted() }}</p>
                        </div>
                        <form action="{{ route('account.addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove this address?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="am-btn am-btn--ghost am-btn--sm">Remove</button>
                        </form>
                    </article>
                    @empty
                    <p class="am-account-empty">No saved addresses yet. Add your delivery address below.</p>
                    @endforelse

                    @php
                        $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();
                        $nameSource = old('first_name') ? null : ($defaultAddress?->name ?? $user->name);
                        $nameParts = $nameSource ? explode(' ', $nameSource, 2) : ['', ''];
                        $addressMeta = $defaultAddress
                            ? \App\Models\CustomerAddress::decodeLine2($defaultAddress->address_line2)
                            : ['company' => '', 'country' => 'India'];
                    @endphp

                    <form action="{{ route('account.addresses.store') }}" method="POST" class="am-address-form">
                        @csrf
                        <input type="hidden" name="label" value="{{ old('label', 'Home') }}">
                        @include('partials.am-address-form-grid', [
                            'mode' => 'account',
                            'userEmail' => $user->email,
                            'firstName' => old('first_name', $nameParts[0] ?? ''),
                            'lastName' => old('last_name', $nameParts[1] ?? ''),
                            'company' => old('company', $addressMeta['company'] ?? ''),
                            'street' => old('address_line1', $defaultAddress?->address_line1 ?? ''),
                            'city' => old('city', $defaultAddress?->city ?? $user->city ?? ''),
                            'state' => old('state', $defaultAddress?->state ?? ''),
                            'pincode' => old('pincode', $defaultAddress?->pincode ?? ''),
                            'phone' => old('phone', $defaultAddress?->phone ?? $user->mobile ?? ''),
                            'country' => old('country', $addressMeta['country'] ?? 'India'),
                        ])
                        <label class="am-account-consent am-address-form__default">
                            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', true))> Set as default delivery address
                        </label>
                        <button type="submit" class="am-btn am-btn--dark am-address-form__submit">Update Address</button>
                    </form>
                </div>
            </section>

            <section class="am-card am-account-panel" id="enquiries">
                <div class="am-card__body">
                    <p class="am-card__label">Studio</p>
                    <h2 class="am-card__title">Enquiries</h2>
                    @forelse($enquiries as $lead)
                    <article class="am-account-list-item">
                        <strong>{{ $lead->subject ?: $lead->typeLabel() }}</strong>
                        <p class="am-account-meta">{{ $lead->created_at->format('d M Y') }} · {{ $lead->statusLabel() }}</p>
                    </article>
                    @empty
                    <p class="am-account-empty">No enquiries yet.</p>
                    @endforelse
                </div>
            </section>

            <section class="am-card am-account-panel" id="quotations">
                <div class="am-card__body">
                    <p class="am-card__label">Quotes</p>
                    <h2 class="am-card__title">Quotation Requests</h2>
                    @forelse($quotations as $lead)
                    <article class="am-account-list-item">
                        <strong>{{ $lead->subject ?: $lead->typeLabel() }}</strong>
                        <p class="am-account-meta">{{ $lead->created_at->format('d M Y') }} · {{ $lead->statusLabel() }}</p>
                    </article>
                    @empty
                    <p class="am-account-empty">No quotation requests yet. <a href="{{ route('leads.create') }}">Request a quote</a></p>
                    @endforelse
                </div>
            </section>

            <section class="am-card am-account-panel" id="orders">
                <div class="am-card__body">
                    <p class="am-card__label">Collection</p>
                    <h2 class="am-card__title">Orders</h2>
                    @forelse($orders as $order)
                    <article class="am-account-list-item">
                        <strong>{{ $order->order_number }}</strong>
                        <p class="am-account-meta">{{ $order->created_at->format('d M Y') }} · {{ $order->statusLabel() }} · ₹{{ number_format($order->total, 0) }}</p>
                    </article>
                    @empty
                    <p class="am-account-empty">No orders yet. <a href="{{ route('shop.index') }}">Browse the shop</a></p>
                    @endforelse
                </div>
            </section>

            <section class="am-card am-account-panel" id="professional">
                <div class="am-card__body">
                    <p class="am-card__label">Trade</p>
                    <h2 class="am-card__title">Professional Application</h2>
                    @if($professional)
                    <p><strong>Status:</strong> {{ $professional->statusLabel() }}</p>
                    <p class="am-account-meta">Submitted {{ $professional->created_at->format('d M Y') }}</p>
                    @else
                    <p class="am-account-empty">No professional application on file. <a href="{{ route('professionals.index') }}">Apply for a trade account</a></p>
                    @endif
                </div>
            </section>

            <section class="am-card am-account-panel am-account-panel--actions">
                <div class="am-card__body">
                    <a href="{{ route('cart.index') }}" class="am-btn am-btn--outline am-btn--full">View Cart</a>
                    <a href="{{ route('leads.create') }}" class="am-btn am-btn--outline am-btn--full">Custom Order</a>
                    <form action="{{ route('account.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="am-btn am-btn--dark am-btn--full">Logout</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>
@endsection
