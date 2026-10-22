<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Thread;
use App\Models\User;

$everyone = ['guest', 'unverified', 'banned', 'member', 'moderator', 'admin'];

it('lets everyone read published threads', function (string $role) {
    expect(allows(forumUser($role), 'view', Thread::factory()->create()))->toBeTrue();
})->with($everyone);

it('shows threads awaiting review only to their author and staff', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'view', Thread::factory()->pending()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['moderator', true],
    ['admin', true],
]);

it('shows a pending thread to its author', function () {
    $author = User::factory()->create();

    expect(allows($author, 'view', Thread::factory()->for($author, 'author')->pending()->create()))->toBeTrue();
});

it('shows hidden and deleted threads only to staff', function (string $role, bool $expected) {
    $author = User::factory()->create();
    $hidden = Thread::factory()->for($author, 'author')->hidden()->create();
    $deleted = Thread::factory()->for($author, 'author')->create();
    $deleted->delete();

    $user = $role === 'author' ? $author : forumUser($role);

    expect(allows($user, 'view', $hidden))->toBe($expected)
        ->and(allows($user, 'view', $deleted))->toBe($expected);
})->with([
    ['guest', false],
    ['author', false],
    ['member', false],
    ['moderator', true],
    ['admin', true],
]);

it('lets verified members who are not banned open threads', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'create', [Thread::class, Category::factory()->create()]))->toBe($expected);
})->with([
    ['guest', false],
    ['unverified', false],
    ['banned', false],
    ['member', true],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('keeps locked categories for staff', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'create', [Thread::class, Category::factory()->locked()->create()]))->toBe($expected);
})->with([
    ['member', false],
    ['moderator', true],
    ['admin', true],
]);

it('lets authors edit their own threads until they are locked', function () {
    $author = User::factory()->create();
    $thread = Thread::factory()->for($author, 'author')->create();
    $locked = Thread::factory()->for($author, 'author')->locked()->create();

    expect(allows($author, 'update', $thread))->toBeTrue()
        ->and(allows($author, 'update', $locked))->toBeFalse()
        ->and(allows(User::factory()->create(), 'update', $thread))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'update', $locked))->toBeTrue();
});

it('stops banned or unverified authors from editing', function (string $state) {
    $author = User::factory()->{$state}()->create();

    expect(allows($author, 'update', Thread::factory()->for($author, 'author')->create()))->toBeFalse();
})->with(['banned', 'unverified']);

it('does not let authors edit threads hidden by moderation', function () {
    $author = User::factory()->create();

    expect(allows($author, 'update', Thread::factory()->for($author, 'author')->hidden()->create()))->toBeFalse();
});

it('lets authors delete their thread only while nobody has replied', function () {
    $author = User::factory()->create();
    $quiet = Thread::factory()->for($author, 'author')->create();
    $answered = Thread::factory()->for($author, 'author')->create(['replies_count' => 1]);
    $locked = Thread::factory()->for($author, 'author')->locked()->create();

    expect(allows($author, 'delete', $quiet))->toBeTrue()
        ->and(allows($author, 'delete', $answered))->toBeFalse()
        ->and(allows($author, 'delete', $locked))->toBeFalse()
        ->and(allows(User::factory()->create(), 'delete', $quiet))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'delete', $answered))->toBeTrue();
});

it('reserves restoring, pinning, locking and moving for staff', function (string $ability, string $role, bool $expected) {
    $thread = Thread::factory()->create();

    if ($ability === 'restore') {
        $thread->delete();
    }

    expect(allows(forumUser($role), $ability, $thread))->toBe($expected);
})->with(['restore', 'pin', 'lock', 'move'])->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('lets verified members who are not banned reply', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'reply', Thread::factory()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['unverified', false],
    ['banned', false],
    ['member', true],
    ['moderator', true],
    ['admin', true],
]);

it('keeps replies in locked threads and locked categories for staff', function (string $role, bool $expected) {
    $lockedThread = Thread::factory()->locked()->create();
    $inLockedCategory = Thread::factory()->for(Category::factory()->locked())->create();
    $user = forumUser($role);

    expect(allows($user, 'reply', $lockedThread))->toBe($expected)
        ->and(allows($user, 'reply', $inLockedCategory))->toBe($expected);
})->with([
    ['member', false],
    ['moderator', true],
    ['admin', true],
]);

it('does not take replies on threads awaiting review, even from their author', function () {
    $author = User::factory()->create();

    expect(allows($author, 'reply', Thread::factory()->for($author, 'author')->pending()->create()))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'reply', Thread::factory()->pending()->create()))->toBeTrue();
});

it('lets the thread author and staff choose the solution', function () {
    $author = User::factory()->create();
    $thread = Thread::factory()->for($author, 'author')->create();
    $locked = Thread::factory()->for($author, 'author')->locked()->create();

    expect(allows($author, 'markSolution', $thread))->toBeTrue()
        ->and(allows($author, 'markSolution', $locked))->toBeFalse()
        ->and(allows(User::factory()->create(), 'markSolution', $thread))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'markSolution', $locked))->toBeTrue()
        ->and(allows(User::factory()->banned()->create(), 'markSolution', $thread))->toBeFalse();
});

it('lets verified members follow the threads they can read', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'subscribe', Thread::factory()->create()))->toBe($expected);
})->with([
    ['guest', false],
    ['unverified', false],
    ['banned', false],
    ['member', true],
    ['moderator', true],
]);

it('does not let members follow threads they cannot read', function () {
    expect(allows(User::factory()->create(), 'subscribe', Thread::factory()->hidden()->create()))->toBeFalse();
});

it('offers summaries once a thread has enough replies', function () {
    $member = User::factory()->create();
    $short = Thread::factory()->create(['replies_count' => 4]);
    $long = Thread::factory()->create(['replies_count' => 5]);

    expect(allows($member, 'summarize', $short))->toBeFalse()
        ->and(allows($member, 'summarize', $long))->toBeTrue()
        ->and(allows(null, 'summarize', $long))->toBeFalse()
        ->and(allows(User::factory()->banned()->create(), 'summarize', $long))->toBeFalse();
});
