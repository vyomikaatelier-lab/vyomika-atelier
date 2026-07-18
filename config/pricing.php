<?php

/**
 * Single source of truth for studio sq ft pricing.
 *
 * Consumed by App\Models\Product (Laravel/Blade — authoritative) and by
 * database/scripts/export-pricing-json.php, which writes public/data/pricing.json
 * for the static preview (public/preview.html + public/js/preview-router.js).
 *
 * Regenerate the preview copy after changing these values:
 *   php database/scripts/export-pricing-json.php
 */
return [
    'base_sqft_rate' => 1800,
    'black_finish_multiplier' => 1.3,
];
