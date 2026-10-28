<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every night, the day that just entered the booking horizon gets its
// sessions. Times are the studio's local time.
Schedule::command('sessions:generate')
    ->dailyAt('00:10')
    ->timezone(config()->string('studio.timezone'))
    ->withoutOverlapping();

// Every night, the classes whose attendance closed (the day after them)
// count as attended for whoever nobody marked as a no-show.
Schedule::command('attendance:close')
    ->dailyAt('00:15')
    ->timezone(config()->string('studio.timezone'))
    ->withoutOverlapping();
