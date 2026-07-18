@component('mail::message')
# New order

A new order has been placed on the storefront.

**Order:** {{ $order->order_number }}  
**Customer:** {{ $order->customer_name }}  
**Email:** {{ $order->customer_email }}  
**Phone:** {{ $order->customer_phone }}  
**Total:** ₹{{ number_format($order->total, 0) }}  
**Status:** {{ $order->statusLabel() }}

@component('mail::table')
| Item | Qty | Total |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ₹{{ number_format($item->total, 0) }} |
@endforeach
@endcomponent

@component('mail::button', ['url' => $adminOrderUrl])
View in admin
@endcomponent

@endcomponent
