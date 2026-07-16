<div id="va-order-modal" class="am-popup-modal am-order-modal" aria-hidden="true">
    <div class="am-popup-modal__backdrop" data-close-popup-form></div>
    <div class="am-popup-modal__panel" role="dialog" aria-labelledby="am-popup-form-title" aria-modal="true">
        <button type="button" class="am-popup-modal__close" data-close-popup-form aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        <div class="am-popup-modal__inner">
            <header class="am-popup-modal__header">
                <p class="am-popup-modal__eyebrow">Leave your requirement</p>
                <h2 id="am-popup-form-title" class="am-popup-modal__title">Complete your order</h2>
                <p class="am-popup-modal__subtitle" data-popup-subtitle hidden></p>
            </header>

            <div class="am-popup-modal__order-summary" data-popup-order-summary hidden>
                <p class="va-modal-service-label am-popup-modal__summary-service"></p>
                <div class="am-calculator__result am-popup-modal__summary-calc">
                    <div class="am-calculator__result-row">
                        <span>Dimensions</span>
                        <span id="va-modal-dim-display"></span>
                    </div>
                    <div class="am-calculator__result-row am-calculator__result-row--price">
                        <span>Estimated price</span>
                        <span id="va-modal-price-display"></span>
                    </div>
                </div>
            </div>

            <form action="{{ \App\Support\StorefrontUrl::to('leads.store', [], '/leads') }}" method="POST" enctype="multipart/form-data" class="am-popup-form" id="va-order-form">
                @csrf
                <input type="hidden" name="type" id="am-popup-type" value="order_now">
                <input type="hidden" name="subject" id="va-modal-subject">
                <input type="hidden" name="service_slug" id="va-modal-service-slug">
                <input type="hidden" name="design_slug" id="va-modal-design-slug">
                <input type="hidden" name="calculated_price" id="va-modal-price">
                <input type="hidden" name="dimensions" id="va-modal-dimensions">
                <input type="hidden" name="unit_type" id="va-modal-unit">
                <input type="hidden" name="finish" id="va-modal-finish">
                <input type="hidden" name="preferred_contact" value="whatsapp">

                <div class="am-popup-form__field">
                    <label for="va-modal-product">Product / enquiry</label>
                    <input type="text" id="va-modal-product" class="am-popup-form__highlight" readonly value="" data-popup-context-field>
                </div>

                <div class="am-popup-form__row">
                    <div class="am-popup-form__field">
                        <label for="va-modal-name">Name</label>
                        <input type="text" name="name" id="va-modal-name" required class="am-input" autocomplete="name">
                    </div>

                    <div class="am-popup-form__field">
                        <label for="va-modal-phone">Phone</label>
                        <input type="tel" name="phone" id="va-modal-phone" required class="am-input" autocomplete="tel">
                    </div>
                </div>

                <div class="am-popup-form__field">
                    <label for="va-modal-email">Email</label>
                    <input type="email" name="email" id="va-modal-email" required class="am-input" autocomplete="email">
                </div>

                <div class="am-popup-form__field">
                    <label for="va-modal-message">Enquiry requirement</label>
                    <textarea name="message" id="va-modal-message" required rows="2" class="am-input am-textarea" placeholder="Dimensions, finish, timeline…"></textarea>
                </div>

                <div class="am-popup-form__field">
                    <label for="va-modal-drawing">Reference file <span class="am-popup-form__optional">(optional)</span></label>
                    <input type="file" name="drawing" id="va-modal-drawing" accept=".jpg,.jpeg,.png,.webp,.pdf" class="am-input am-input--file">
                </div>

                @include('partials.am-popup-form-submit', ['label' => 'Submit Requirement'])
            </form>
        </div>
    </div>
</div>
