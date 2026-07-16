@props(['countryCodes' => [], 'oldCountry' => '+91', 'fieldPrefix' => '', 'stacked' => false])

@php
    $ccId = $fieldPrefix !== '' ? $fieldPrefix . '-country_code' : 'country_code';
    $mobileId = $fieldPrefix !== '' ? $fieldPrefix . '-mobile' : 'mobile';
@endphp

<div class="am-account-phone {{ $stacked ? 'am-account-phone--stacked' : '' }}">
    <div class="am-account-phone__code">
        <label for="{{ $ccId }}" class="am-sr-only">Country code</label>
        <select name="country_code" id="{{ $ccId }}" class="am-input am-input--select" required>
            @foreach($countryCodes as $code => $label)
            <option value="{{ $code }}" @selected(old('country_code', $oldCountry) === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="am-account-phone__number">
        <label for="{{ $mobileId }}" class="am-sr-only">Mobile number</label>
        <input type="tel" name="mobile" id="{{ $mobileId }}" value="{{ old('mobile') }}" placeholder="Mobile number" required class="am-input" inputmode="numeric" autocomplete="tel-national">
    </div>
</div>
