<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('campaigns:sync-ad-platforms')->dailyAt('05:30');
Schedule::command('campaigns:generate-insights')->dailyAt('08:00');
Schedule::command('financial:generate-contract-transactions')->dailyAt('06:00');
Schedule::command('automations:check-date-triggers')->dailyAt('07:00');
