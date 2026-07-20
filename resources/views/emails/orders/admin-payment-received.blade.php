@component('mail::message')
# Payment received

Verified payment for order **{{ $order->order_number }}**.

**Customer:** {{ $order->customer_name }} ({{ $order->customer_email }})  
**Amount:** ₹{{ number_format($order->total, 0) }}  
**Payment ID:** {{ $order->payment_id }}

@component('mail::table')
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }}@if($item->finish_name) ({{ $item->finish_name }})@endif | {{ $item->quantity }} | ₹{{ number_format($item->total, 0) }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => $adminOrderUrl])
View in admin
@endcomponent

@endcomponent
