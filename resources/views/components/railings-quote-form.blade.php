@props(['formConfig' => []])

@php
    $customerTypes = $formConfig['customer_types'] ?? [];
    $usage = $formConfig['usage'] ?? [];
    $categories = $formConfig['railing_categories'] ?? [];
    $layouts = $formConfig['layout_shapes'] ?? [];
    $materials = $formConfig['materials'] ?? [];
    $finishes = $formConfig['finishes'] ?? [];
    $heights = $formConfig['heights'] ?? [];
    $timelines = $formConfig['timelines'] ?? [];
@endphp

<form action="{{ route('leads.store') }}" method="POST" enctype="multipart/form-data" class="am-form-stack am-lead-form am-railings-form" id="am-railings-quote-form">
    @csrf
    <input type="hidden" name="type" value="railing_quotation">
    <input type="hidden" name="service_slug" value="railings">
    <input type="hidden" name="subject" value="Railing quotation request">

    <fieldset class="am-railings-form__group">
        <legend class="am-pro-form__label">Customer type *</legend>
        <div class="am-railings-form__choices">
            @foreach($customerTypes as $value => $label)
            <label class="am-railings-form__choice">
                <input type="radio" name="customer_type" value="{{ $value }}" required @checked(old('customer_type') === $value)>
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="am-railings-form__group">
        <legend class="am-pro-form__label">Indoor or exterior use *</legend>
        <div class="am-railings-form__choices">
            @foreach($usage as $value => $label)
            <label class="am-railings-form__choice">
                <input type="radio" name="usage" value="{{ $value }}" required @checked(old('usage') === $value)>
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="am-railings-form__group">
        <legend class="am-pro-form__label">Railing category *</legend>
        <div class="am-railings-form__choices am-railings-form__choices--grid">
            @foreach($categories as $value => $label)
            <label class="am-railings-form__choice">
                <input type="radio" name="railing_category" value="{{ $value }}" required @checked(old('railing_category') === $value)>
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="am-railings-form__group">
        <legend class="am-pro-form__label">Staircase / layout shape *</legend>
        <div class="am-railings-form__choices am-railings-form__choices--grid">
            @foreach($layouts as $value => $label)
            <label class="am-railings-form__choice">
                <input type="radio" name="layout_shape" value="{{ $value }}" required @checked(old('layout_shape') === $value)>
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>

    <div class="am-pro-form__grid am-pro-form__grid--2">
        <fieldset class="am-railings-form__group">
            <legend class="am-pro-form__label">Material *</legend>
            <select name="material" class="am-input am-input--select" required>
                <option value="">Select material</option>
                @foreach($materials as $value => $label)
                <option value="{{ $value }}" @selected(old('material') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </fieldset>
        <fieldset class="am-railings-form__group">
            <legend class="am-pro-form__label">Finish *</legend>
            <select name="finish" class="am-input am-input--select" required>
                <option value="">Select finish</option>
                @foreach($finishes as $value => $label)
                <option value="{{ $value }}" @selected(old('finish') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </fieldset>
    </div>

    <div class="am-pro-form__grid">
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Approx. running feet *</span>
            <input type="number" name="running_feet" value="{{ old('running_feet') }}" min="1" max="500" step="0.5" placeholder="e.g. 24" required class="am-input">
        </label>
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Number of steps</span>
            <input type="number" name="step_count" value="{{ old('step_count') }}" min="0" max="100" placeholder="If applicable" class="am-input">
        </label>
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Railing height *</span>
            <select name="railing_height" class="am-input am-input--select" required>
                <option value="">Select height</option>
                @foreach($heights as $value => $label)
                <option value="{{ $value }}" @selected(old('railing_height') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Project location *</span>
            <input type="text" name="project_location" value="{{ old('project_location') }}" placeholder="City / site address" required class="am-input">
        </label>
    </div>

    <label class="am-pro-form__field">
        <span class="am-pro-form__label">Timeline *</span>
        <select name="timeline" class="am-input am-input--select" required>
            <option value="">Select timeline</option>
            @foreach($timelines as $value => $label)
            <option value="{{ $value }}" @selected(old('timeline') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>

    <label class="am-pro-form__field">
        <span class="am-pro-form__label">Upload image or drawing (optional)</span>
        <input type="file" name="drawing" accept="image/jpeg,image/png,image/webp,application/pdf" class="am-input am-input--file">
        <span class="am-railings-form__hint">JPG, PNG, WebP or PDF — max 8 MB</span>
    </label>

    <div class="am-pro-form__grid">
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name *" required class="am-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Mobile *" required class="am-input">
        <input type="tel" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="WhatsApp (if different)" class="am-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email *" required class="am-input">
    </div>

    <textarea name="message" placeholder="Additional notes — landing details, handrail profile, site access…" rows="4" class="am-input am-textarea">{{ old('message') }}</textarea>

    <select name="preferred_contact" class="am-input am-input--select">
        <option value="">Preferred contact method</option>
        <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
        <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
    </select>

    <x-form-protection-fields form-key="railing_quotation" />

    <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Quotation Request</button>
    <p class="am-pro-form__note">Our studio team typically responds within one business day with next steps and an indicative quotation.</p>
</form>
