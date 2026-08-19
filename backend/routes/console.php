<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Laravel's scheduler only runs if something invokes `php artisan schedule:run`
| every minute — a cron entry in production, or `php artisan schedule:work` in
| a terminal during development. Nothing here fires on its own without that.
|
*/

// The supplier catalogue has no live feed behind it, so nothing else ever puts
// stock back or moves a price. Left alone the suppliers drain to empty and the
// platform can no longer buy anything. Run before the working day so the
// morning shift opens onto a catalogue that has already been topped up.
Schedule::command('catalogue:refresh')
    ->dailyAt('03:00')
    ->withoutOverlapping();
