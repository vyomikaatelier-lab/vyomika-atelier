@props([
    'serviceSlug' => '',
    'designSlug' => '',
    'subject' => '',
    'type' => 'service_inquiry',
    'showBudget' => true,
])

<div class="va-lead-form">
    <form action="{{ route('leads.store') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        @if($serviceSlug)<input type="hidden" name="service_slug" value="{{ $serviceSlug }}">@endif
        @if($designSlug)<input type="hidden" name="design_slug" value="{{ $designSlug }}">@endif

        <input type="text" name="name" value="{{ old('name') }}" placeholder="Your Name" required class="va-input">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="va-input">
        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="Phone / WhatsApp" required class="va-input">
        <input type="text" name="subject" value="{{ old('subject', $subject) }}" placeholder="Subject (optional)" class="va-input">
        @if($showBudget)
            <input type="text" name="budget" value="{{ old('budget') }}" placeholder="Budget range (optional)" class="va-input">
        @endif
        <select name="preferred_contact" class="va-input">
            <option value="">Preferred contact method</option>
            <option value="email" @selected(old('preferred_contact') === 'email')>Email</option>
            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone</option>
            <option value="whatsapp" @selected(old('preferred_contact') === 'whatsapp')>WhatsApp</option>
        </select>
        <textarea name="message" placeholder="Describe your requirements — dimensions, material, finish, timeline…" required rows="5" class="va-input">{{ old('message') }}</textarea>
        <button type="submit" class="va-btn-primary w-full text-center">Submit Enquiry</button>
    </form>
</div>
