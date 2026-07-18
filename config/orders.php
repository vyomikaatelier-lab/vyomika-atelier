<?php

return [
    /** Hours before unpaid pending orders are cancelled and reservations released. */
    'pending_expiry_hours' => (int) env('ORDER_PENDING_EXPIRY_HOURS', 24),
];
