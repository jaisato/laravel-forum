<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the maintenance commands', function (string $command, string $expression) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression);
})->with([
    'horizon metrics' => ['horizon:snapshot', '*/5 * * * *'],
    'expired api tokens' => ['sanctum:prune-expired --hours=24', '0 0 * * *'],
    'old notifications' => ['forum:prune-notifications', '0 0 * * *'],
    'counter check' => ['forum:recount', '0 0 * * 0'],
]);
