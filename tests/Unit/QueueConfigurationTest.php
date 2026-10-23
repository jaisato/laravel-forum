<?php

declare(strict_types=1);

it('lets every queued job time out before Redis hands it to another worker', function () {
    // Horizon's supervisors (and `queue:listen` in compose.yaml) stop a job
    // at its timeout; a job still running at retry_after is released again,
    // so a slow AI call would run twice.
    $timeouts = collect(config('horizon.defaults'))
        ->concat(collect(config('horizon.environments'))->flatten(1))
        ->pluck('timeout')
        ->filter();

    expect($timeouts)->not->toBeEmpty()
        ->and(config('queue.connections.redis.retry_after'))
        ->toBeGreaterThan($timeouts->max());
});
