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
    document.getElementById('rzp-button').onclick = function () {
        const options = {
            key: @json($razorpayKey),
            amount: {{ (int) round($order->total * 100) }},
            currency: 'INR',
            name: 'Vyomika Atelier LLP',
            description: 'Order {{ $order->order_number }}',
            order_id: @json($order->razorpay_order_id),
            prefill: {
                name: @json($order->customer_name),
                email: @json($order->customer_email),
                contact: @json($order->customer_phone),
            },
            handler: function (response) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = @json(route('checkout.pay.verify', $order));
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = @json(csrf_token());
                form.appendChild(csrf);
                ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature'].forEach(function (field) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field;
                    input.value = response[field];
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            },
            theme: { color: '#b38b42' }
        };
        new Razorpay(options).open();
    };
</script>
@endpush
@endsection
