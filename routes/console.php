<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contabilium:sync --orders-only --recent')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('contabilium:sync --products-only')
    ->hourly()
    ->withoutOverlapping(10);
