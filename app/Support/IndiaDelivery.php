<?php

namespace App\Support;

use App\Models\Order;

/**
 * India self-service checkout includes shipping in the product price.
 * Any other destination is an enquiry until staff confirm shipping.
 * Posted prices, totals, and eligibility flags are never consulted.
 */
final class IndiaDelivery
{
    public const CUSTOMER_NOTE = 'Shipping included within India. For international delivery, our team will confirm shipping charges and estimated delivery time before payment.';

    public const READY_STOCK_ESTIMATE = 'Within India, ready stock is an estimate of 5–12 business days.';

    public const MADE_TO_ORDER_ESTIMATE = 'Within India, made-to-order work is an estimate of 15–35 business days.';

    public const ENQUIRY_SAVED = 'Your international delivery enquiry has been saved. Our team will confirm shipping charges and estimated delivery time before payment.';

    public const PAYMENT_BLOCKED = 'International delivery is not available for online payment. Submit a shipping enquiry and our team will confirm shipping charges and estimated delivery time before payment.';

    public const STALE_ORDER_REFRESH = 'This order includes an older shipping charge, so online payment cannot be started. Please submit a new checkout. Shipping is included within India.';

    public const STALE_ORDER_RETIRED = 'An older order that included a separate shipping charge has been cancelled. Please place a new order. Shipping is included within India.';

    public const STALE_SHIPPING_SUPPORT = 'This order includes an older shipping charge, so online payment cannot be started. Please contact the studio for help. Saved payment details have not been changed.';

    public const SHIPPING_INCLUDED_INDIA = 'Shipping included within India';

    public const SHIPPING_QUOTED = 'Shipping quoted separately';

    public const MERCHANDISE_SUBTOTAL = 'Merchandise subtotal';

    public const ENQUIRY_HINT = 'Shipping quoted separately. Our team will confirm shipping charges, estimated delivery time, and import-duty responsibility before payment.';

    public static function isIndia(?string $country): bool
    {
        return strcasecmp(trim((string) $country), 'India') === 0;
    }

    public static function hasObsoleteShippingCharge(Order $order): bool
    {
        return (float) $order->shipping_cost > 0;
    }

    public static function canInitiateSelfServicePayment(Order $order): bool
    {
        return self::isIndia($order->country) && ! self::hasObsoleteShippingCharge($order);
    }
}
