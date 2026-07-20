@extends('layouts.store')

@section('title', 'Pay — Order ' . $order->order_number)

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Secure Payment',
    'title' => 'Complete Payment',
    'subtitle' => 'Order #' . $order->order_number,
])

<section class="am-page-body am-page-body--checkout-pay">
    <div class="am-checkout-flow am-checkout-flow--centered am-checkout-flow--pay">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Checkout', 'url' => route('checkout.index')],
            ['label' => 'Payment'],
        ]])

        @include('partials.am-checkout-steps', ['current' => 3])

        @if(session('error'))
        <p class="am-checkout-notice am-checkout-notice--error" role="alert">{{ session('error') }}</p>
        @endif

        <p class="am-checkout-pay__error" id="rzp-error" role="alert" hidden></p>

        <div class="am-checkout-pay-layout">
            <div class="am-card am-checkout-pay-card">
                <div class="am-card__body">
                    <p class="am-checkout-pay-card__eyebrow">Amount due</p>
                    <p class="am-checkout-pay-card__amount">₹{{ number_format($order->total, 0) }}</p>
                    <p class="am-checkout-pay-card__text">Choose a payment method below. You will continue on Razorpay’s secure payment page (not a popup).</p>
                </div>
            </div>

            <div class="am-card am-checkout-panel am-checkout-panel--payment">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Payment method</h2>
                    <p class="am-checkout-panel__hint">Indian UPI, debit/credit card, or net banking</p>

                    <div class="am-checkout-pay-methods" role="list">
                        <button type="button" class="am-checkout-pay-method" data-pay-method="upi" role="listitem">
                            <span class="am-checkout-pay-method__icon" aria-hidden="true">UPI</span>
                            <span class="am-checkout-pay-method__body">
                                <span class="am-checkout-pay-method__label">Pay with UPI</span>
                                <span class="am-checkout-pay-method__hint">Google Pay, PhonePe, Paytm, BHIM</span>
                            </span>
                            <span class="am-checkout-pay-method__arrow" aria-hidden="true">→</span>
                        </button>
                        <button type="button" class="am-checkout-pay-method" data-pay-method="card" role="listitem">
                            <span class="am-checkout-pay-method__icon" aria-hidden="true">Card</span>
                            <span class="am-checkout-pay-method__body">
                                <span class="am-checkout-pay-method__label">Debit / Credit Card</span>
                                <span class="am-checkout-pay-method__hint">Visa, Mastercard, RuPay</span>
                            </span>
                            <span class="am-checkout-pay-method__arrow" aria-hidden="true">→</span>
                        </button>
                        <button type="button" class="am-checkout-pay-method" data-pay-method="netbanking" role="listitem">
                            <span class="am-checkout-pay-method__icon" aria-hidden="true">Bank</span>
                            <span class="am-checkout-pay-method__body">
                                <span class="am-checkout-pay-method__label">Net Banking</span>
                                <span class="am-checkout-pay-method__hint">All major Indian banks</span>
                            </span>
                            <span class="am-checkout-pay-method__arrow" aria-hidden="true">→</span>
                        </button>
                    </div>

                    @if(str_starts_with($razorpayKey ?? '', 'rzp_test_'))
                    <p class="am-checkout-pay-test-hint">Test mode: UPI <code>test@razorpay</code> · Card <code>4111 1111 1111 1111</code></p>
                    @endif

                    @include('partials.am-pdp-checkout-trust')
                </div>
            </div>

            <div class="am-card am-checkout-pay-details">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Order details</h2>
                    <dl class="am-checkout-pay-details__list am-checkout-pay-details__list--centered">
                        <div>
                            <dt>Order number</dt>
                            <dd>#{{ $order->order_number }}</dd>
                        </div>
                        <div>
                            <dt>Customer</dt>
                            <dd>{{ $order->customer_name }}</dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>{{ $order->customer_phone }}</dd>
                        </div>
                        <div>
                            <dt>Ship to</dt>
                            <dd>
                                {{ $order->shipping_address }},
                                {{ $order->city }}@if($order->state), {{ $order->state }}@endif
                                {{ $order->pincode }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <p class="am-checkout-pay-card__help am-checkout-pay-card__help--centered">
                <button type="button" class="am-link-btn" data-open-contact-studio data-contact-context="Checkout help">Need help?</button>
                <span>Confirmation to {{ $order->customer_email }}</span>
            </p>
        </div>
    </div>
</section>

@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const storeOrderId = @json($order->id);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
    const errorEl = document.getElementById('rzp-error');
    const createOrderUrl = @json(route('api.create-order'));
    const verifyUrl = @json(route('checkout.pay.verify', $order));
    const methodButtons = document.querySelectorAll('[data-pay-method]');
    let paying = false;

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.hidden = !message;
        if (message) {
            errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function setBusy(busy) {
        paying = busy;
        methodButtons.forEach((btn) => {
            btn.disabled = busy;
            btn.classList.toggle('is-loading', busy);
        });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Something went wrong. Please try again.');
            }
            return data;
        });
    }

    methodButtons.forEach((button) => {
        button.addEventListener('click', async function () {
            if (paying) return;

            showError('');
            setBusy(true);

            try {
                const orderData = await postJson(createOrderUrl, { store_order_id: storeOrderId });

                const options = {
                    key: orderData.key,
                    amount: orderData.amount,
                    currency: orderData.currency,
                    name: 'Vyomika Atelier LLP',
                    description: 'Order {{ $order->order_number }}',
                    order_id: orderData.order_id,
                    callback_url: verifyUrl,
                    redirect: true,
                    prefill: {
                        name: @json($order->customer_name),
                        email: @json($order->customer_email),
                        contact: @json($order->customer_phone),
                    },
                    theme: { color: '#b38b42' },
                    method: {
                        upi: button.dataset.payMethod === 'upi',
                        card: button.dataset.payMethod === 'card',
                        netbanking: button.dataset.payMethod === 'netbanking',
                        wallet: false,
                        emi: false,
                        paylater: false,
                    },
                };

                const rzp = new Razorpay(options);
                rzp.on('payment.failed', function (event) {
                    const reason = event.error?.description || event.error?.reason || 'Payment failed. Please try again.';
                    showError(reason);
                    setBusy(false);
                });
                rzp.open();
            } catch (err) {
                showError(err.message);
                setBusy(false);
            }
        });
    });
})();
</script>
@endpush
@endsection
