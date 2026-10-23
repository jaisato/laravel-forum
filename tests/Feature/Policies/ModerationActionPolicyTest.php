<?php

declare(strict_types=1);

use App\Models\ModerationAction;

it('opens the moderation log to staff only', function (string $role, bool $expected) {
    $user = forumUser($role);

    expect(allows($user, 'viewAny', ModerationAction::class))->toBe($expected)
        ->and(allows($user, 'view', ModerationAction::factory()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('keeps the moderation log read only, even for administrators', function (string $ability) {
    $admin = forumUser('admin');

    expect(allows($admin, $ability, ModerationAction::factory()->create()))->toBeFalse();
})->with(['update', 'delete']);
