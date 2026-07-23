@props(['countryCodes' => [], 'oldCountry' => '+91', 'fieldPrefix' => '', 'stacked' => false])

@php
    $ccId = $fieldPrefix !== '' ? $fieldPrefix . '-country_code' : 'country_code';
    $mobileId = $fieldPrefix !== '' ? $fieldPrefix . '-mobile' : 'mobile';

    $countryFlag = static function (string $iso): string {
        $iso = strtoupper($iso);
        if (strlen($iso) !== 2) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($iso[0]) - ord('A'), 'UTF-8')
            . mb_chr(0x1F1E6 + ord($iso[1]) - ord('A'), 'UTF-8');
    };
@endphp

<div class="am-account-phone {{ $stacked ? 'am-account-phone--stacked' : '' }}">
    <div class="am-account-phone__code">
        <label for="{{ $ccId }}" class="am-sr-only">Country code</label>
        <select name="country_code" id="{{ $ccId }}" class="am-input am-input--select am-account-phone__select" required>
            @foreach($countryCodes as $code => $meta)
            @php
                $name = is_array($meta) ? ($meta['name'] ?? $code) : preg_replace('/\s*\([^)]+\)\s*$/', '', (string) $meta);
                $iso = is_array($meta) ? ($meta['iso'] ?? '') : '';
                $flag = $iso !== '' ? $countryFlag($iso) : '';
                $optionLabel = trim($flag !== '' ? $flag . ' ' . $code : $name . ' (' . $code . ')');
            @endphp
            <option value="{{ $code }}" title="{{ $name }}" @selected(old('country_code', $oldCountry) === $code)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    </div>
    <div class="am-account-phone__number">
        <label for="{{ $mobileId }}" class="am-sr-only">Mobile number</label>
        <input type="tel" name="mobile" id="{{ $mobileId }}" value="{{ old('mobile') }}" placeholder="Mobile number" required class="am-input" inputmode="numeric" autocomplete="tel-national">
    </div>
</div>
