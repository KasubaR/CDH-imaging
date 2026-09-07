<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('images:purge-expired')
    ->daily()
    ->withoutOverlapping();

Schedule::command('images:purge-stale-uploads')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('backup:database')
    ->daily()
    ->at('01:00')
    ->withoutOverlapping();

Schedule::command('backup:images')
    ->daily()
    ->at('01:30')
    ->withoutOverlapping();

Schedule::command('backup:prune')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping();
