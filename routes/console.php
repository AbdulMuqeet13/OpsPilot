<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('briefs:generate')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Demo upkeep: clear expired visitor sandboxes hourly, and restore the showcase nightly.
Schedule::command('demo:reset --sandboxes-only')
    ->hourly()
    ->when(fn (): bool => (bool) config('demo.enabled'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('demo:reset')
    ->dailyAt('03:00')
    ->when(fn (): bool => (bool) config('demo.enabled'))
    ->withoutOverlapping()
    ->onOneServer();
