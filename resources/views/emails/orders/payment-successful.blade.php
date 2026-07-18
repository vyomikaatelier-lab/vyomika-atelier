@component('mail::message')
# Payment confirmed

Thank you — we have received your payment for order **{{ $order->order_number }}**.

@component('mail::table')
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ₹{{ number_format($item->total, 0) }} |
@endforeach
@endcomponent

**Subtotal:** ₹{{ number_format($order->subtotal, 0) }}  
**Shipping:** ₹{{ number_format($order->shipping_cost, 0) }}  
**Total:** ₹{{ number_format($order->total, 0) }}

**Delivery address**  
{{ $order->customer_name }}  
{{ $order->shipping_address }}  
{{ $order->city }}@if($order->state), {{ $order->state }}@endif — {{ $order->pincode }}

We will begin processing your order shortly.

Questions? Contact us at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
