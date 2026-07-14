@extends('layouts.app')

@section('title', 'Pay — Order ' . $order->order_number)

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <h1 class="font-serif text-3xl mb-2">Complete Payment</h1>
    <p class="text-brand-700 mb-2">Order #{{ $order->order_number }}</p>
    <p class="text-2xl font-medium mb-8">₹{{ number_format($order->total, 0) }}</p>

    @if(session('error'))
        <p class="text-red-600 mb-4">{{ session('error') }}</p>
    @endif

    <button id="rzp-button" class="bg-brand-900 text-white px-8 py-3 text-sm uppercase tracking-wider hover:bg-brand-700 transition">
        Pay with Razorpay
    </button>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.getElementById('rzp-button').onclick = function () {
        const options = {
            key: @json($razorpayKey),
            amount: {{ (int) round($order->total * 100) }},
            currency: 'INR',
            name: 'VYOMIKA ATELIER',
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
            theme: { color: '#2d2419' }
        };

        new Razorpay(options).open();
    };
</script>
@endsection
