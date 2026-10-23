<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

/*
 * The abilities only the admin panel asks for (spec §4.8). The panel runs
 * in strict authorization mode, so each of its checks needs its method.
 */

it('lets the staff browse posts, threads and members in the panel', function (string $role, bool $expected) {
    $user = forumUser($role);

    expect(allows($user, 'viewAny', Post::class))->toBe($expected)
        ->and(allows($user, 'viewAny', Thread::class))->toBe($expected)
        ->and(allows($user, 'viewAny', User::class))->toBe($expected)
        ->and(allows($user, 'view', User::factory()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('lets only administrators reorder the categories', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'reorder', Category::class))->toBe($expected);
})->with([
    ['member', false],
    ['moderator', false],
    ['banned admin', false],
    ['admin', true],
]);
