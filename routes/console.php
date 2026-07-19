<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('office:backup:check')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('office:operations:check')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
