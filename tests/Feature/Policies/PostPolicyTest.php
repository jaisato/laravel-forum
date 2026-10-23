<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

it('shows published posts to everyone', function (string $role) {
    expect(allows(forumUser($role), 'view', Post::factory()->create()))->toBeTrue();
})->with(['guest', 'unverified', 'banned', 'member', 'moderator']);

it('shows posts awaiting review to their author and staff only', function () {
    $author = User::factory()->create();
    $post = Post::factory()->for($author, 'author')->pending()->create();

    expect(allows($author, 'view', $post))->toBeTrue()
        ->and(allows(User::factory()->create(), 'view', $post))->toBeFalse()
        ->and(allows(null, 'view', $post))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'view', $post))->toBeTrue();
});

it('shows hidden and deleted posts to staff only', function () {
    $author = User::factory()->create();
    $hidden = Post::factory()->for($author, 'author')->hidden()->create();
    $deleted = Post::factory()->for($author, 'author')->create();
    $deleted->delete();
    $moderator = User::factory()->moderator()->create();

    expect(allows($author, 'view', $hidden))->toBeFalse()
        ->and(allows($author, 'view', $deleted))->toBeFalse()
        ->and(allows($moderator, 'view', $hidden))->toBeTrue()
        ->and(allows($moderator, 'view', $deleted))->toBeTrue();
});

it('stops showing hidden posts to a banned moderator', function () {
    $hidden = Post::factory()->hidden()->create();
    $pending = Post::factory()->pending()->create();
    $bannedModerator = forumUser('banned moderator');

    expect(allows($bannedModerator, 'view', $hidden))->toBeFalse()
        ->and(allows($bannedModerator, 'view', $pending))->toBeFalse()
        ->and(Post::query()->visibleTo($bannedModerator)->pluck('id')->all())
        ->not->toContain($hidden->id)
        ->not->toContain($pending->id);
});

it('does not leak the posts of a thread nobody else may read', function () {
    $post = Post::factory()->for(Thread::factory()->hidden())->create();

    expect(allows(User::factory()->create(), 'view', $post))->toBeFalse()
        ->and(allows(User::factory()->admin()->create(), 'view', $post))->toBeTrue();
});

it('lets authors edit their replies unless the thread is locked or the post hidden', function () {
    $author = User::factory()->create();
    $reply = Post::factory()->for($author, 'author')->create();
    $inLocked = Post::factory()->for(Thread::factory()->locked())->for($author, 'author')->create();
    $hidden = Post::factory()->for($author, 'author')->hidden()->create();

    expect(allows($author, 'update', $reply))->toBeTrue()
        ->and(allows($author, 'update', $inLocked))->toBeFalse()
        ->and(allows($author, 'update', $hidden))->toBeFalse()
        ->and(allows(User::factory()->create(), 'update', $reply))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'update', $inLocked))->toBeTrue();
});

it('stops banned or unverified authors from editing or deleting their posts', function (string $state) {
    $author = User::factory()->{$state}()->create();
    $reply = Post::factory()->for($author, 'author')->create();

    expect(allows($author, 'update', $reply))->toBeFalse()
        ->and(allows($author, 'delete', $reply))->toBeFalse();
})->with(['banned', 'unverified']);

it('lets authors delete their replies until the thread is locked', function () {
    $author = User::factory()->create();
    $reply = Post::factory()->for($author, 'author')->create();
    $inLocked = Post::factory()->for(Thread::factory()->locked())->for($author, 'author')->create();

    expect(allows($author, 'delete', $reply))->toBeTrue()
        ->and(allows($author, 'delete', $inLocked))->toBeFalse()
        ->and(allows(User::factory()->create(), 'delete', $reply))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'delete', $inLocked))->toBeTrue();
});

it('treats deleting the opening post as deleting the thread', function () {
    $author = User::factory()->create();
    $quiet = Thread::factory()->for($author, 'author')->create();
    $answered = Thread::factory()->for($author, 'author')->create(['replies_count' => 3]);

    expect(allows($author, 'delete', $quiet->firstPost))->toBeTrue()
        ->and(allows($author, 'delete', $answered->firstPost))->toBeFalse();
});

it('reserves restoring, hiding and approving posts for staff', function (string $ability, string $role, bool $expected) {
    $post = Post::factory()->create();

    if ($ability === 'restore') {
        $post->delete();
    }

    expect(allows(forumUser($role), $ability, $post))->toBe($expected);
})->with(['restore', 'hide', 'approve'])->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('lets members react to and report what others wrote', function (string $ability, string $role, bool $expected) {
    expect(allows(forumUser($role), $ability, Post::factory()->create()))->toBe($expected);
})->with(['react', 'report'])->with([
    ['guest', false],
    ['unverified', false],
    ['banned', false],
    ['member', true],
    ['moderator', true],
    ['admin', true],
]);

it('does not let anyone react to or report their own posts', function (string $ability, string $role) {
    $author = forumUser($role);

    expect(allows($author, $ability, Post::factory()->for($author, 'author')->create()))->toBeFalse();
})->with(['react', 'report'])->with(['member', 'admin']);

it('freezes reactions in locked threads but keeps reports open', function () {
    $post = Post::factory()->for(Thread::factory()->locked())->create();
    $member = User::factory()->create();

    expect(allows($member, 'react', $post))->toBeFalse()
        ->and(allows($member, 'report', $post))->toBeTrue();
});

it('does not take reactions or reports on posts members cannot read', function (string $ability) {
    $member = User::factory()->create();

    expect(allows($member, $ability, Post::factory()->hidden()->create()))->toBeFalse()
        ->and(allows($member, $ability, Post::factory()->pending()->create()))->toBeFalse();
})->with(['react', 'report']);

it('takes no reactions or reports in a deleted thread, however it was loaded', function (string $ability) {
    $thread = Thread::factory()->create();
    $post = Post::factory()->for($thread)->create();
    $thread->delete();
    // The thread page loads deleted threads for staff and sets the relation.
    $post->setRelation('thread', Thread::withTrashed()->findOrFail($thread->id));

    expect(allows(User::factory()->create(), $ability, $post))->toBeFalse();
})->with(['react', 'report']);
