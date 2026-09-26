@component('mail::message')
@if($order->hasDurableCapturedPaymentEvidence())
# Payment received

Verified payment for order **{{ $order->order_number }}**.
@else
# Payment not confirmed

No captured payment is stored for order **{{ $order->order_number }}**.
@endif

**Customer:** {{ $order->customer_name }} ({{ $order->customer_email }})  
**Amount:** ₹{{ number_format($order->total, 0) }}

@component('mail::table')
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }}{{ $item->finish_name ? ' ('.$item->finish_name.')' : '' }}{{ $item->size_label ? ' — '.$item->size_label : '' }} | {{ $item->quantity }} | ₹{{ number_format($item->total, 0) }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => $adminOrderUrl])
View in admin
@endcomponent

@endcomponent
