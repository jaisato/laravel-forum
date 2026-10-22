<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Enums\ModerationActionType;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Tag;
use App\Models\User;

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

    app(DeleteThread::class)->handle($thread, $author);

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
