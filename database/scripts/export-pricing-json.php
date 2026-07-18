<?php

/**
 * Sync config/pricing.php into public/data/pricing.json for the static preview.
 *
 * This keeps public/preview.html + public/js/preview-router.js reading the exact
 * same base sq ft rate and black-finish multiplier as the Laravel/Blade app
 * (App\Models\Product::baseSqFtRate() / blackSqFtRate()), instead of a
 * hardcoded ₹1800 baked into the JS.
 *
 * Run after changing config/pricing.php:
 *   php database/scripts/export-pricing-json.php
 */
$root = dirname(__DIR__, 2);

$pricing = require $root . '/config/pricing.php';

$baseRate = (int) ($pricing['base_sqft_rate'] ?? 1800);
$blackMultiplier = (float) ($pricing['black_finish_multiplier'] ?? 1.3);
$blackRate = (int) round($baseRate * $blackMultiplier);

$payload = [
    'base_sqft_rate' => $baseRate,
    'black_finish_multiplier' => $blackMultiplier,
    'black_sqft_rate' => $blackRate,
    'generated_at' => date('c'),
];

file_put_contents(
    $root . '/public/data/pricing.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Exported pricing.json (base={$baseRate}, black={$blackRate})\n";
