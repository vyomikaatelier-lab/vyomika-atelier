@props([
    'formKey' => 'contact',
    'showIntent' => true,
    'intentRequired' => true,
])

@php
    $protection = app(\App\Services\FormProtectionService::class);
    $turnstile = app(\App\Services\TurnstileService::class);
    $honeypot = config('form_protection.honeypot_field', 'va_contact_url');
    $intents = config('form_protection.enquiry_intents', []);
@endphp

<div class="va-form-protection" data-form-protection data-form-key="{{ $formKey }}">
    <input type="hidden" name="form_loaded_at" value="{{ $protection->formLoadedToken($formKey) }}">
    <input type="hidden" name="turnstile_fallback_token" value="{{ $turnstile->fallbackToken($formKey) }}">
    <input type="hidden" name="turnstile_unavailable" value="0" data-turnstile-unavailable>

    <div class="va-form-protection__honeypot" aria-hidden="true">
        <label for="{{ $honeypot }}-{{ $formKey }}">Leave blank</label>
        <input
            type="text"
            name="{{ $honeypot }}"
            id="{{ $honeypot }}-{{ $formKey }}"
            value=""
            tabindex="-1"
            autocomplete="off"
        >
    </div>

    @if($showIntent)
        <div class="am-form-stack__field">
            <label for="enquiry_intent-{{ $formKey }}" class="am-form-stack__label">
                Project timeline / intent @if($intentRequired)<span aria-hidden="true">*</span>@endif
            </label>
            <select
                name="enquiry_intent"
                id="enquiry_intent-{{ $formKey }}"
                class="am-input am-input--select"
                @if($intentRequired) required @endif
            >
                <option value="">Select one</option>
                @foreach($intents as $value => $label)
                    <option value="{{ $value }}" @selected(old('enquiry_intent') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="va-form-protection__turnstile" data-turnstile-widget @if($turnstile->siteKey()) data-sitekey="{{ $turnstile->siteKey() }}" @endif></div>

    <div class="va-form-protection__fallback" data-turnstile-fallback hidden>
        <p class="va-form-protection__fallback-note">Security check could not load. Please confirm you are a real person.</p>
        <label class="va-form-protection__fallback-check">
            <input type="checkbox" name="human_confirmation" value="1">
            <span>I confirm this is a genuine enquiry</span>
        </label>
    </div>
</div>

@once
    @push('styles')
        <style>
            .va-form-protection__honeypot {
                position: absolute;
                left: -10000px;
                top: auto;
                width: 1px;
                height: 1px;
                overflow: hidden;
            }
            .va-form-protection__turnstile { margin: 0.75rem 0; min-height: 65px; }
            .va-form-protection__fallback { margin: 0.75rem 0; padding: 0.75rem; border: 1px solid var(--am-border, #ddd); border-radius: 4px; }
            .va-form-protection__fallback-note { font-size: 0.875rem; margin-bottom: 0.5rem; }
            .va-form-protection__fallback-check { display: flex; gap: 0.5rem; align-items: flex-start; font-size: 0.875rem; }
        </style>
    @endpush
    @push('scripts')
        <script src="{{ asset('js/form-protection.js') }}?v={{ @filemtime(public_path('js/form-protection.js')) ?: time() }}" defer></script>
    @endpush
@endonce
