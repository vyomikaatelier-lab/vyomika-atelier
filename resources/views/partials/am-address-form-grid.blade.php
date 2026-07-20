@props([
    'mode' => 'account',
    'userEmail' => null,
    'firstName' => '',
    'lastName' => '',
    'company' => '',
    'street' => '',
    'houseBuilding' => '',
    'locality' => '',
    'landmark' => '',
    'city' => '',
    'state' => '',
    'pincode' => '',
    'phone' => '',
    'altMobile' => '',
    'country' => 'India',
    'addressType' => 'home',
    'floor' => '',
    'liftAvailable' => null,
    'deliveryInstructions' => '',
])

@php
    $isCheckout = $mode === 'checkout';
    $phoneName = $isCheckout ? 'customer_phone' : 'phone';
    $countries = config('addresses.countries', ['India']);
    $indiaStates = config('addresses.india_states', []);
    $addressTypes = config('addresses.address_types', ['home' => 'Home']);

    $selectedCountry = old('country', $country);
    $countryIsOther = $selectedCountry && ! in_array($selectedCountry, $countries, true);
    $countrySelectValue = $countryIsOther ? 'Other' : $selectedCountry;
    $isIndia = $countrySelectValue === 'India';
    $houseValue = old('house_building', $houseBuilding ?: ($isCheckout ? '' : $street));
    $streetValue = old('street', $street);
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
        <label for="am-addr-phone">Mobile <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="tel" id="am-addr-phone" name="{{ $phoneName }}" value="{{ old($phoneName, $phone) }}" placeholder="Mobile" required class="am-input" autocomplete="tel">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-alt">Alt. mobile <span class="am-address-form__optional">(optional)</span></label>
        <input type="tel" id="am-addr-alt" name="alt_mobile" value="{{ old('alt_mobile', $altMobile) }}" placeholder="Alternate mobile" class="am-input" autocomplete="tel">
    </div>

    <div class="am-address-form__field am-address-form__field--full">
        <label for="am-addr-email">Email <span class="am-address-form__req" aria-hidden="true">*</span></label>
        @if($isCheckout)
            <input type="email" id="am-addr-email" name="customer_email" value="{{ old('customer_email', $userEmail) }}" placeholder="Email" required class="am-input" autocomplete="email">
        @else
            <input type="email" id="am-addr-email" name="email" value="{{ old('email', $userEmail) }}" placeholder="Email" required class="am-input" autocomplete="email">
        @endif
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-company">Company <span class="am-address-form__optional">(optional)</span></label>
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
        <label for="am-addr-house">House / Building <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-house" name="house_building" value="{{ $houseValue }}" placeholder="Flat, house, building" required class="am-input" autocomplete="address-line1">
    </div>
    <div class="am-address-form__field am-address-form__field--full">
        <label for="am-addr-street">Street / Area <span class="am-address-form__optional">(optional)</span></label>
        <input type="text" id="am-addr-street" name="street" value="{{ $streetValue }}" placeholder="Street, colony, area" class="am-input" autocomplete="street-address">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-locality">Locality <span class="am-address-form__optional">(optional)</span></label>
        <input type="text" id="am-addr-locality" name="locality" value="{{ old('locality', $locality) }}" placeholder="Locality" class="am-input">
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-landmark">Landmark <span class="am-address-form__optional">(optional)</span></label>
        <input type="text" id="am-addr-landmark" name="landmark" value="{{ old('landmark', $landmark) }}" placeholder="Nearby landmark" class="am-input">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-city">City / Town <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-city" name="city" value="{{ old('city', $city) }}" placeholder="City / Town" required class="am-input" autocomplete="address-level2">
    </div>

    <div class="am-address-form__field" data-state-field>
        <label for="am-addr-state">State / Province <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <select id="am-addr-state-select" name="state" class="am-input am-input--select" data-state-select @if(!$isIndia) hidden @endif autocomplete="address-level1">
            <option value="">Select state</option>
            @foreach($indiaStates as $stateOption)
            <option value="{{ $stateOption }}" @selected(old('state', $state) === $stateOption)>{{ $stateOption }}</option>
            @endforeach
        </select>
        <input type="text" id="am-addr-state-text" name="state" value="{{ old('state', $state) }}" placeholder="State / Province" class="am-input" data-state-text @if($isIndia) hidden @endif autocomplete="address-level1">
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-zip">Pincode / ZIP <span class="am-address-form__req" aria-hidden="true">*</span></label>
        <input type="text" id="am-addr-zip" name="pincode" value="{{ old('pincode', $pincode) }}" placeholder="Pincode / ZIP" required class="am-input" autocomplete="postal-code" data-pincode-input @if($isIndia) pattern="[1-9][0-9]{5}" maxlength="6" inputmode="numeric" @endif>
    </div>
    <div class="am-address-form__field">
        <label for="am-addr-type">Address type</label>
        <select id="am-addr-type" name="address_type" class="am-input am-input--select">
            @foreach($addressTypes as $typeValue => $typeLabel)
            <option value="{{ $typeValue }}" @selected(old('address_type', $addressType) === $typeValue)>{{ $typeLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="am-address-form__field">
        <label for="am-addr-floor">Floor <span class="am-address-form__optional">(optional)</span></label>
        <input type="text" id="am-addr-floor" name="floor" value="{{ old('floor', $floor) }}" placeholder="Floor" class="am-input">
    </div>
    <div class="am-address-form__field">
        <label class="am-address-form__default" for="am-addr-lift">
            <input type="checkbox" id="am-addr-lift" name="lift_available" value="1" @checked(old('lift_available', $liftAvailable))> Lift available
        </label>
    </div>

    <div class="am-address-form__field am-address-form__field--full">
        <label for="am-addr-instructions">Delivery instructions <span class="am-address-form__optional">(optional)</span></label>
        <textarea id="am-addr-instructions" name="delivery_instructions" rows="2" class="am-input am-textarea" placeholder="Gate code, preferred time…">{{ old('delivery_instructions', $deliveryInstructions) }}</textarea>
    </div>
</div>

<input type="hidden" name="{{ $isCheckout ? 'customer_name' : 'name' }}" id="am-addr-full-name" value="{{ old($isCheckout ? 'customer_name' : 'name') }}">
