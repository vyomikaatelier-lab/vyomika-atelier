<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daily lead summary
    |--------------------------------------------------------------------------
    |
    | LEADS_DAILY_SUMMARY_ENABLED
    |
    | false or absent: leads:daily-summary is not registered. The scheduler
    | does not send the daily lead email.
    |
    | true: the summary is scheduled at 08:00 Asia/Kolkata. Enable this only
    | after outbound mail delivery has been verified.
    |
    */
    'daily_summary_enabled' => filter_var(env('LEADS_DAILY_SUMMARY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
