<?php

declare(strict_types=1);

it('describes the forum commands in the command list', function (string $command, string $description) {
    $this->artisan('list', ['namespace' => 'forum'])
        ->expectsOutputToContain($description)
        ->assertSuccessful();
})->with([
    ['forum:make-admin', 'Give an existing user the administrator role'],
    ['forum:prune-notifications', 'Delete old read notifications'],
    ['forum:recount', 'Recompute the denormalised forum counters and reputation'],
]);
