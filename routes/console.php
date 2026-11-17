<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ZATCA: simplified invoices must be reported within 24 hours. Each sale already retries what failed;
// this covers quiet hours. Needs the cron: * * * * * cd /path/to/app && php artisan schedule:run
Schedule::command('zatca:submit-pending')->hourly()->withoutOverlapping();
