<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// every minute run your custom command
Schedule::command('crypto:predict --interval=1 --auto-trade')
    ->everyMinute()
    ->withoutOverlapping();

// every 5 minutes run your custom command
Schedule::command('crypto:predict --interval=5 --auto-trade')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// every 15 minutes run your custom command
Schedule::command('crypto:predict --interval=15 --auto-trade')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// every 30 minutes run your custom command
Schedule::command('crypto:predict --interval=30 --auto-trade')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// every hour run your custom command
Schedule::command('crypto:predict --interval=60 --auto-trade')
    ->hourly()
    ->withoutOverlapping();

// every 4 hours run your custom command    
Schedule::command('crypto:predict --interval=240 --auto-trade')
    ->cron('0 */4 * * *')
    ->withoutOverlapping();

// every day at midnight run your custom command
Schedule::command('crypto:predict --interval=1440 --auto-trade')
    ->daily()
    ->withoutOverlapping();

// every 1 week run your custom command    
Schedule::command('crypto:predict --interval=10080 --auto-trade')
    ->weekly()
    ->withoutOverlapping();

// every 3 weeks run your custom command
Schedule::command('crypto:predict --interval=21600 --auto-trade')
    ->cron('0 0 */21 * *')
    ->withoutOverlapping();

// update ohlcv data every 1 day at midnight
Schedule::command('ohlcv:fetch-all')
    ->daily()
    ->withoutOverlapping();
