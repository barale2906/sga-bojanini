<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sga:check-expirations')
    ->dailyAt('06:00')
    ->name('sga:check-expirations');

Schedule::command('sga:check-reorder-points')
    ->hourly()
    ->name('sga:check-reorder-points');
