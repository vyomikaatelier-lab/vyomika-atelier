@props([
    'title' => 'Quick quote',
    'type' => 'service_inquiry',
    'serviceSlug' => '',
    'subject' => '',
    'submitLabel' => 'Send enquiry',
    'messagePlaceholder' => 'Brief project details — location, dimensions, timeline…',
    'formKey' => 'service_inquiry',
])

@php
    $fieldId = 'studio-spotlight-' . preg_replace('/[^a-z0-9-]+/i', '-', $serviceSlug ?: 'enquiry');
@endphp

<form
    action="{{ route('leads.store') }}"
    method="POST"
    class="am-studio-spotlight-form am-form-stack am-form-stack--compact"
    id="{{ $fieldId }}-form"
>
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    @if($serviceSlug)<input type="hidden" name="service_slug" value="{{ $serviceSlug }}">@endif
    @if($subject)<input type="hidden" name="subject" value="{{ $subject }}">@endif
    <input type="hidden" name="enquiry_intent" value="general_enquiry">

    <p class="am-studio-spotlight-form__label">Quick enquiry</p>
    <h4 class="am-studio-spotlight-form__title">{{ $title }}</h4>

    <input
        type="text"
        name="name"
        id="{{ $fieldId }}-name"
        value="{{ old('name') }}"
        placeholder="Your name"
        required
        autocomplete="name"
        class="am-input am-input--compact"
    >
    <div class="am-studio-spotlight-form__row">
        <input
            type="tel"
            name="phone"
            id="{{ $fieldId }}-phone"
            value="{{ old('phone') }}"
            placeholder="Phone / WhatsApp"
            required
            autocomplete="tel"
            class="am-input am-input--compact"
        >
        <input
            type="email"
            name="email"
            id="{{ $fieldId }}-email"
            value="{{ old('email') }}"
            placeholder="Email"
            required
            autocomplete="email"
            class="am-input am-input--compact"
        >
    </div>
    <textarea
        name="message"
        id="{{ $fieldId }}-message"
        placeholder="{{ $messagePlaceholder }}"
        required
        rows="2"
        class="am-input am-textarea am-input--compact"
    >{{ old('message') }}</textarea>

    <x-form-protection-fields :form-key="$formKey" :show-intent="false" />
</form>
