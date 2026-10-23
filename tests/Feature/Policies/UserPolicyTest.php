<?php

declare(strict_types=1);

use App\Models\User;

it('lets moderators ban members and administrators ban everyone but administrators', function (string $actor, string $target, bool $expected) {
    expect(allows(forumUser($actor), 'ban', forumUser($target)))->toBe($expected);
})->with([
    ['member', 'member', false],
    ['moderator', 'member', true],
    ['moderator', 'moderator', false],
    ['moderator', 'admin', false],
    ['admin', 'member', true],
    ['admin', 'moderator', true],
    ['admin', 'admin', false],
    ['banned moderator', 'member', false],
]);

it('does not let anyone ban themselves', function (string $role) {
    $user = forumUser($role);

    expect(allows($user, 'ban', $user))->toBeFalse();
})->with(['moderator', 'admin']);

it('lets administrators change the role of other users only', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    expect(allows($admin, 'changeRole', $member))->toBeTrue()
        ->and(allows($admin, 'changeRole', $admin))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'changeRole', $member))->toBeFalse()
        ->and(allows(User::factory()->admin()->banned()->create(), 'changeRole', $member))->toBeFalse();
});
