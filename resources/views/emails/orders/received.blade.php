@component('mail::message')
# Order received

Thank you for your order with **Vyomika Atelier**.

**Order number:** {{ $order->order_number }}

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

Your payment is not complete yet. Please finish checkout on our website to confirm your order.

If you need help, contact us at [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
