<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Enums\ModerationActionType;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('takes the whole thread out of every counter', function () {
    $category = Category::factory()->create();
    $author = User::factory()->create();
    $replier = User::factory()->create();
    $tag = Tag::factory()->create();
    $kept = forumThread($author, $category);
    $thread = forumThread($author, $category, tagIds: [$tag->id]);
    forumReply($thread, $replier);
    forumReply($thread, $replier);
    forumReply($kept, $replier);

    // Only staff may delete a thread somebody replied to.
    app(DeleteThread::class)->handle($thread, User::factory()->moderator()->create());

    expect($thread->fresh()->trashed())->toBeTrue()
        ->and($category->fresh())->threads_count->toBe(1)->posts_count->toBe(2)
        ->and($author->fresh())->threads_count->toBe(1)->posts_count->toBe(1)
        ->and($replier->fresh()->posts_count)->toBe(1)
        ->and($tag->fresh()->threads_count)->toBe(0);
});

it('does not count a thread deletion twice', function () {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);

    app(DeleteThread::class)->handle($thread, $thread->author);
    app(DeleteThread::class)->handle($thread, $thread->author);

    expect($category->fresh())->threads_count->toBe(0)->posts_count->toBe(0);
});

it('logs thread deletions made by staff with their reason', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    app(DeleteThread::class)->handle($thread, $moderator, 'Duplicate');

    $action = ModerationAction::query()->sole();

    expect($action->action)->toBe(ModerationActionType::ThreadDeleted)
        ->and($action->subject->is($thread))->toBeTrue()
        ->and($action->reason)->toBe('Duplicate');
});

it('refuses an author whose thread got a reply after the request was authorised', function () {
    $thread = forumThread();
    // The copy the request authorised the deletion on, while nobody had replied.
    $authorised = Thread::query()->findOrFail($thread->id);
    forumReply($thread);

    expect(fn () => app(DeleteThread::class)->handle($authorised, $thread->author))
        ->toThrow(AuthorizationException::class);

    expect($thread->fresh()->trashed())->toBeFalse();

    // Staff may still delete it.
    app(DeleteThread::class)->handle($authorised, User::factory()->moderator()->create());

    expect($thread->fresh()->trashed())->toBeTrue();
});
