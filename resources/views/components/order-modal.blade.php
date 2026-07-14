<div id="va-order-modal" class="va-modal" aria-hidden="true">
    <div class="va-modal-backdrop" data-close-modal></div>
    <div class="va-modal-panel" role="dialog" aria-labelledby="va-modal-title">
        <button type="button" class="va-modal-close" data-close-modal aria-label="Close">&times;</button>
        <p class="va-label mb-2">Order Request</p>
        <h2 id="va-modal-title" class="font-serif text-3xl text-brand-900 mb-2">Complete your order</h2>
        <p class="text-sm text-brand-500 mb-6 va-modal-service-label"></p>

        <form action="{{ route('leads.store') }}" method="POST" class="space-y-4" id="va-order-form">
            @csrf
            <input type="hidden" name="type" value="order_now">
            <input type="hidden" name="service_slug" id="va-modal-service-slug">
            <input type="hidden" name="design_slug" id="va-modal-design-slug">
            <input type="hidden" name="calculated_price" id="va-modal-price">
            <input type="hidden" name="dimensions" id="va-modal-dimensions">
            <input type="hidden" name="unit_type" id="va-modal-unit">

            <div class="bg-brand-50 border border-brand-200 p-4 text-sm">
                <div class="flex justify-between mb-1"><span class="text-brand-500">Dimensions</span><span id="va-modal-dim-display" class="text-brand-900"></span></div>
                <div class="flex justify-between"><span class="text-brand-500">Estimated Price</span><span id="va-modal-price-display" class="font-serif text-lg text-brand-900"></span></div>
            </div>

            <input type="text" name="name" placeholder="Your Name" required class="va-input">
            <input type="email" name="email" placeholder="Email" required class="va-input">
            <input type="tel" name="phone" placeholder="Phone / WhatsApp" required class="va-input">
            <input type="text" name="subject" id="va-modal-subject" placeholder="Subject" class="va-input">
            <textarea name="message" placeholder="Additional notes — installation address, timeline, specifications…" required rows="4" class="va-input"></textarea>
            <button type="submit" class="va-btn-primary w-full text-center">Submit Order Request</button>
        </form>
    </div>
</div>
