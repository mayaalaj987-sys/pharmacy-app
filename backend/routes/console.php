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

// Expiry costs a pharmacy real money and used to say nothing until the till
// refused a box, months after the last moment anything could be done. Early
// enough in the day that a warning is actionable before the shop is busy.
Schedule::command('stock:expiry-check')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Nothing ever deleted a notification, so the table grew without limit and the
// bell with it. Read ones go after a month; unread ones stay however old they
// are, because an unread notification is still asking for something.
Schedule::command('notifications:prune')
    ->dailyAt('04:00')
    ->withoutOverlapping();
