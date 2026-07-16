@props(['formConfig' => []])

@php
    $interests = $formConfig['interest_options'] ?? [];
    $years = $formConfig['years_options'] ?? [];
    $volumes = $formConfig['volume_options'] ?? [];
@endphp

<form action="{{ route('leads.store') }}" method="POST" class="am-form-stack am-lead-form am-pro-form" id="am-professional-application-form">
    @csrf
    <input type="hidden" name="type" value="professional_application">
    <input type="hidden" name="subject" value="Professional partnership application">

    <div class="am-pro-form__grid">
        <input type="text" name="name" value="{{ old('name') }}" placeholder="Full name *" required class="am-input">
        <input type="text" name="company" value="{{ old('company') }}" placeholder="Company / practice name *" required class="am-input">
        <input type="text" name="role" value="{{ old('role') }}" placeholder="Your role *" required class="am-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Business email *" required class="am-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp *" required class="am-input">
        <input type="text" name="city" value="{{ old('city') }}" placeholder="City / state *" required class="am-input">
        <input type="url" name="website" value="{{ old('website') }}" placeholder="Website or portfolio link" class="am-input">
        <input type="text" name="gst_number" value="{{ old('gst_number') }}" placeholder="GST / business registration no." class="am-input">
    </div>

    <div class="am-pro-form__grid am-pro-form__grid--2">
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Years in business</span>
            <select name="years_in_business" class="am-input am-input--select">
                <option value="">Select</option>
                @foreach($years as $opt)
                <option value="{{ $opt }}" @selected(old('years_in_business') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <label class="am-pro-form__field">
            <span class="am-pro-form__label">Estimated project volume</span>
            <select name="budget" class="am-input am-input--select">
                <option value="">Select</option>
                @foreach($volumes as $opt)
                <option value="{{ $opt }}" @selected(old('budget') === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if(count($interests))
    <fieldset class="am-pro-form__interests">
        <legend class="am-pro-form__label">Primary interest areas</legend>
        <div class="am-pro-form__checks">
            @foreach($interests as $value => $label)
            <label class="am-pro-form__check">
                <input type="checkbox" name="interest_areas[]" value="{{ $value }}" @checked(in_array($value, old('interest_areas', [])))>
                <span>{{ $label }}</span>
            </label>
            @endforeach
        </div>
    </fieldset>
    @endif

    <textarea name="message" placeholder="Current projects, typical specifications, timeline and how we can support your practice… *" required rows="5" class="am-input am-textarea">{{ old('message') }}</textarea>

    <select name="preferred_contact" class="am-input am-input--select">
        <option value="">Preferred contact method</option>
        <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
        <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
        <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
    </select>

    <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Application</button>
    <p class="am-pro-form__note">By submitting, you agree to be contacted regarding partnership verification. We typically respond within 2–3 business days.</p>
</form>
