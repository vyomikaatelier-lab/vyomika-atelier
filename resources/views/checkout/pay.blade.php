@extends('layouts.store')

@section('title', 'Pay — Order ' . $order->order_number)

@section('content')
@include('partials.am-page-hero', [
    'label' => 'Secure Payment',
    'title' => 'Complete Payment',
    'subtitle' => 'Order #' . $order->order_number,
])

<section class="am-page-body">
    <div class="am-container am-checkout-flow am-checkout-flow--centered">
        @include('partials.am-breadcrumbs', ['items' => [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Checkout', 'url' => route('checkout.index')],
            ['label' => 'Payment'],
        ]])

        @include('partials.am-checkout-steps', ['current' => 3])

        <div class="am-checkout-stack">
            <div class="am-card am-checkout-pay-card">
                <div class="am-card__body">
                    <p class="am-checkout-pay-card__eyebrow">Amount due</p>
                    <p class="am-checkout-pay-card__amount">₹{{ number_format($order->total, 0) }}</p>
                    <p class="am-checkout-pay-card__text">Pay with UPI, debit/credit card, or net banking via Razorpay.</p>

                    @if(session('error'))
                        <p class="am-checkout-pay__error" role="alert">{{ session('error') }}</p>
                    @endif
                    <p class="am-checkout-pay__error" id="rzp-error" role="alert" hidden></p>

                    <button type="button" id="rzp-button" class="am-btn am-btn--primary am-btn--lg am-btn--full">Pay Now</button>

                    <p class="am-checkout-pay-card__help">
                        <button type="button" class="am-link-btn" data-open-contact-studio data-contact-context="Checkout help">Need help?</button>
                        <span>Confirmation to {{ $order->customer_email }}</span>
                    </p>
                </div>
            </div>

            <div class="am-card am-checkout-pay-details">
                <div class="am-card__body">
                    <h2 class="am-checkout-panel__title">Order details</h2>
                    <dl class="am-checkout-pay-details__list">
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

            @include('partials.am-pdp-checkout-trust')
        </div>
    </div>
</section>

@push('scripts')
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    const storeOrderId = @json($order->id);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
    const payButton = document.getElementById('rzp-button');
    const errorEl = document.getElementById('rzp-error');
    const createOrderUrl = @json(route('api.create-order'));
    const verifyPaymentUrl = @json(route('api.verify-payment'));

    function showError(message) {
        if (!errorEl) return;
        errorEl.textContent = message;
        errorEl.hidden = !message;
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

    payButton.addEventListener('click', async function () {
        showError('');
        payButton.disabled = true;
        payButton.textContent = 'Opening payment…';

        try {
            const orderData = await postJson(createOrderUrl, { store_order_id: storeOrderId });

            const options = {
                key: orderData.key,
                amount: orderData.amount,
                currency: orderData.currency,
                name: 'Vyomika Atelier LLP',
                description: 'Order {{ $order->order_number }}',
                order_id: orderData.order_id,
                prefill: {
                    name: @json($order->customer_name),
                    email: @json($order->customer_email),
                    contact: @json($order->customer_phone),
                },
                theme: { color: '#b38b42' },
                handler: async function (response) {
                    payButton.disabled = true;
                    payButton.textContent = 'Verifying payment…';
                    showError('');

                    try {
                        const result = await postJson(verifyPaymentUrl, {
                            store_order_id: storeOrderId,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature,
                        });
                        window.location.href = result.redirect || @json(route('checkout.success', $order));
                    } catch (err) {
                        showError(err.message);
                        payButton.disabled = false;
                        payButton.textContent = 'Pay Now';
                    }
                },
                modal: {
                    ondismiss: function () {
                        showError('Payment cancelled. You can try again when ready.');
                        payButton.disabled = false;
                        payButton.textContent = 'Pay Now';
                    },
                },
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (event) {
                const reason = event.error?.description || event.error?.reason || 'Payment failed. Please try again.';
                showError(reason);
                payButton.disabled = false;
                payButton.textContent = 'Pay Now';
            });
            rzp.open();
        } catch (err) {
            showError(err.message);
        } finally {
            payButton.disabled = false;
            payButton.textContent = 'Pay Now';
        }
    });
})();
</script>
@endpush
@endsection
