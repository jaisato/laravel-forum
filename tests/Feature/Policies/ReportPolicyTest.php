<?php

declare(strict_types=1);

use App\Models\Report;

it('opens the report queue to staff only', function (string $role, bool $expected) {
    $user = forumUser($role);

    expect(allows($user, 'viewAny', Report::class))->toBe($expected)
        ->and(allows($user, 'resolve', Report::factory()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);
