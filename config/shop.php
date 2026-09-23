<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Primary shop category
    |--------------------------------------------------------------------------
    |
    | The generic /shop URL permanently redirects here. Use an existing
    | storefront shop category slug. Mirror Frames is the default because it
    | is the configured primary shop collection.
    |
    */
    'primary_category' => env('SHOP_PRIMARY_CATEGORY', 'mirror-frames'),

    'gallery_per_page' => 12,

    'buy_now_ttl_minutes' => 120,
];
