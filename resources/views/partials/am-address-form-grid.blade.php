@props([
    'mode' => 'account',
    'userEmail' => null,
    'firstName' => '',
    'lastName' => '',
    'company' => '',
    'street' => '',
    'city' => '',
    'state' => '',
    'pincode' => '',
    'phone' => '',
    'country' => 'India',
])

@php
    $isCheckout = $mode === 'checkout';
    $streetName = $isCheckout ? 'shipping_address' : 'address_line1';
    $cityName = $isCheckout ? 'city' : 'city';
    $stateName = $isCheckout ? 'state' : 'state';
    $pinName = $isCheckout ? 'pincode' : 'pincode';
    $phoneName = $isCheckout ? 'customer_phone' : 'phone';

    $countries = [
        'India',
        'United States',
        'United Kingdom',
        'Canada',
        'Australia',
        'United Arab Emirates',
        'Singapore',
        'Germany',
        'France',
        'Italy',
        'Spain',
        'Netherlands',
        'Switzerland',
        'Belgium',
        'Sweden',
        'Norway',
        'Denmark',
        'Japan',
        'South Korea',
        'China',
        'Hong Kong',
        'Malaysia',
        'Thailand',
        'Indonesia',
        'Philippines',
        'Saudi Arabia',
        'Qatar',
        'Kuwait',
        'Bahrain',
        'Oman',
        'South Africa',
        'New Zealand',
        'Brazil',
        'Mexico',
        'Portugal',
        'Ireland',
        'Austria',
        'Turkey',
        'Israel',
        'Other',
    ];

    $selectedCountry = old('country', $country);
    $countryIsOther = $selectedCountry && ! in_array($selectedCountry, $countries, true);
    $countrySelectValue = $countryIsOther ? 'Other' : $selectedCountry;
@endphp

<div class="am-address-form__grid">
    <div class="am-address-form__field">
        <label for="am-addr-first">First Name <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-first" name="first_name" value="{{ old('first_name', $firstName) }}" placeholder="First Name" required class="am-input" autocomplete="given-name">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-last">Last Name <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-last" name="last_name" value="{{ old('last_name', $lastName) }}" placeholder="Last Name" required class="am-input" autocomplete="family-name">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-company">Company name <span class="am-address-form__optional">(optional)</span></label>
        <input type="text" id="am-addr-company" name="company" value="{{ old('company', $company) }}" placeholder="Company" class="am-input" autocomplete="organization">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-country">Country / Region <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <select id="am-addr-country" name="country" class="am-input am-input--select" required autocomplete="country-name" data-country-select>
            @foreach($countries as $countryOption)
            <option value="{{ $countryOption }}" @selected($countrySelectValue === $countryOption)>{{ $countryOption }}</option>
            @endforeach
        </select>
    </div>

    <div class="am-address-form__field am-address-form__field--full" data-country-other-wrap @if(!$countryIsOther) hidden @endif>
        <label for="am-addr-country-other">Specify country <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-country-other" name="country_other" value="{{ old('country_other', $countryIsOther ? $selectedCountry : '') }}" placeholder="Country name" class="am-input" autocomplete="country-name" @if($countryIsOther) required @endif>
    </div>

    <div class="am-address-form__field am-address-form__field--full">
        <label for="am-addr-street">Street Address <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-street" name="{{ $streetName }}" value="{{ old($streetName, $street) }}" placeholder="Street Address" required class="am-input" autocomplete="street-address">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-city">City / Town <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-city" name="{{ $cityName }}" value="{{ old($cityName, $city) }}" placeholder="City / Town" required class="am-input" autocomplete="address-level2">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-state">State / Province <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-state" name="{{ $stateName }}" value="{{ old($stateName, $state) }}" placeholder="State / Province" required class="am-input" autocomplete="address-level1">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-zip">Pincode / ZIP <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-zip" name="{{ $pinName }}" value="{{ old($pinName, $pincode) }}" placeholder="Pincode / ZIP" required class="am-input" autocomplete="postal-code">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-phone">Phone <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="tel" id="am-addr-phone" name="{{ $phoneName }}" value="{{ old($phoneName, $phone) }}" placeholder="Phone" required class="am-input" autocomplete="tel">
    </div>

    <div class="am-address-form__field am-address-form__field--full">
        <label for="am-addr-email">Email <span class="am-address-form__req" aria-hidden="true">*</span></label>
        @if($isCheckout)
            <input type="email" id="am-addr-email" name="customer_email" value="{{ old('customer_email', $userEmail) }}" placeholder="Email" required class="am-input" autocomplete="email">
        @else
            <input type="email" id="am-addr-email" value="{{ $userEmail }}" placeholder="Email" class="am-input" readonly aria-readonly="true">
        @endif
    </div>
</div>

<input type="hidden" name="{{ $isCheckout ? 'customer_name' : 'name' }}" id="am-addr-full-name" value="{{ old($isCheckout ? 'customer_name' : 'name') }}">
