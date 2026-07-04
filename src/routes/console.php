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

Schedule::command('sga:analyze-conditions')
    ->everyThirtyMinutes()
    ->name('sga:analyze-conditions');

Schedule::command('sga:generate-condition-reports')
    ->dailyAt('23:00')
    ->name('sga:generate-condition-reports');

Schedule::command('sga:cleanup-report-exports')
    ->dailyAt('02:00')
    ->name('sga:cleanup-report-exports');

Schedule::command('sga:cleanup-pending-movements')
    ->dailyAt('03:00')
    ->name('sga:cleanup-pending-movements');
