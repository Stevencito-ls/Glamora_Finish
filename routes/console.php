<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('glamora:send-abandoned-carts --minutes=60 --max-reminders=3 --cooldown-hours=24')
    ->hourly()
    ->withoutOverlapping();
