<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-pending')->everyFifteenMinutes();

if (config('leads.daily_summary_enabled')) {
    Schedule::command('leads:daily-summary')
        ->dailyAt('08:00')
        ->timezone('Asia/Kolkata');
}
