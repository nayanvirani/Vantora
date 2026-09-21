<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F-08 weekly monitoring runs as its own Railway cron service
// (Vantora-monitoring, schedule "0 6 * * 1"), not through Laravel's
// scheduler -- there's no long-running process here to host schedule:run.
