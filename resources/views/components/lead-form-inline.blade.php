@props([
    'serviceSlug' => '',
    'designSlug' => '',
    'subject' => '',
    'type' => 'service_inquiry',
    'showBudget' => true,
])

<div class="am-lead-form">
    <form action="{{ route('leads.store') }}" method="POST" class="am-form-stack">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        @if($serviceSlug)<input type="hidden" name="service_slug" value="{{ $serviceSlug }}">@endif
        @if($designSlug)<input type="hidden" name="design_slug" value="{{ $designSlug }}">@endif

        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="am-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="am-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="am-input">
        <input type="text" name="subject" value="{{ old('subject', $subject) }}" placeholder="Subject (optional)" class="am-input">
        @if($showBudget)
            <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="am-input">
        @endif
        <select name="preferred_contact" class="am-input">
            <option value="">Preferred contact method</option>
            <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
            <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Describe your requirements — dimensions, material, finish, timeline…" required rows="5" class="am-input am-textarea">{{ old('message') }}</textarea>
        <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Enquiry</button>
    </form>
</div>
