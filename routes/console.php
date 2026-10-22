<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| Run by the scheduler container (`php artisan schedule:work`). Every task
| runs on a single server, so several app replicas never repeat them.
|
*/

Schedule::command('horizon:snapshot')->everyFiveMinutes()->onOneServer();

Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

Schedule::command('forum:prune-notifications')->daily()->onOneServer();

Schedule::command('forum:recount')->weekly()->withoutOverlapping()->onOneServer();
