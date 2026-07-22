@php
    $business = $business ?? \App\Support\LegalContent::business();
    $phoneRaw = preg_replace('/\s+/', '', $business['phone'] ?? '');
@endphp
<div class="am-business-details">
    <p class="am-card__label">Business details</p>
    <dl class="am-business-details__list">
        <div>
            <dt>Legal name</dt>
            <dd>{{ $business['legal_name'] ?? '' }}</dd>
        </div>
        @if(!empty($business['brand_name']) && ($business['brand_name'] ?? '') !== ($business['legal_name'] ?? ''))
        <div>
            <dt>Trading as</dt>
            <dd>{{ $business['brand_name'] }}</dd>
        </div>
        @endif
        <div>
            <dt>Address</dt>
            <dd>{{ $business['address'] ?? '' }}</dd>
        </div>
        @if(!empty($business['gstin']))
        <div>
            <dt>GSTIN</dt>
            <dd>{{ $business['gstin'] }}</dd>
        </div>
        @endif
        <div>
            <dt>Phone</dt>
            <dd><a href="tel:{{ $phoneRaw }}">{{ $business['phone'] ?? '' }}</a></dd>
        </div>
        <div>
            <dt>Email</dt>
            <dd><a href="mailto:{{ $business['email'] ?? '' }}">{{ $business['email'] ?? '' }}</a></dd>
        </div>
    </dl>
</div>
