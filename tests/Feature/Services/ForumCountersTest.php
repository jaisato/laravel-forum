<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\RestorePost;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('counts the replies a thread gets while it is not public', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    // The AI holds the opening post, so the whole thread awaits review…
    app(HidePost::class)->handle($thread->firstPost, null);
    // …while a moderator answers the author.
    $reply = forumReply($thread->fresh(), $moderator);

    expect($thread->fresh()->replies_count)->toBe(1);

    app(ApprovePost::class)->handle($thread->fresh()->firstPost, $moderator);

    expect($thread->fresh()->replies_count)->toBe(1)
        // A thread with an answer is no longer the author's to delete.
        ->and(allows($thread->author, 'delete', $thread->fresh()))->toBeFalse()
        ->and(Category::query()->findOrFail($thread->category_id)->posts_count)->toBe(2)
        ->and($moderator->fresh()->posts_count)->toBe(1);

    app(DeletePost::class)->handle($reply, $moderator);

    expect($thread->fresh()->replies_count)->toBe(0)
        ->and(Category::query()->findOrFail($thread->category_id)->posts_count)->toBe(1)
        ->and($moderator->fresh()->posts_count)->toBe(0);

    $this->artisan('forum:recount')->expectsOutputToContain('All counters were already right');
});

it('keeps the public totals out of hidden threads while their replies come and go', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();
    app(HidePost::class)->handle($thread->firstPost, $moderator);
    $reply = forumReply($thread->fresh(), $moderator);

    app(DeletePost::class)->handle($reply, $moderator);
    app(RestorePost::class)->handle($reply->fresh(), $moderator);

    expect($thread->fresh()->replies_count)->toBe(1)
        ->and(Category::query()->findOrFail($thread->category_id)->posts_count)->toBe(0)
        ->and($moderator->fresh()->posts_count)->toBe(0)
        ->and(Thread::query()->whereKey($thread->id)->value('status')->value)->toBe('hidden');

    $this->artisan('forum:recount')->expectsOutputToContain('All counters were already right');
});

it('updates every author of a thread with one statement, whatever their number', function () {
    $thread = forumThread();
    forumReply($thread);
    forumReply($thread);
    forumReply($thread);
    $updates = [];
    DB::listen(function (QueryExecuted $query) use (&$updates): void {
        if (str_starts_with($query->sql, 'update "users"')) {
            $updates[] = $query->sql;
        }
    });

    app(DeleteThread::class)->handle($thread, User::factory()->moderator()->create());

    // One round trip for a busy thread, with the rows locked by id first so
    // two moderators working on threads with the same people cannot
    // deadlock on them.
    expect($updates)->toHaveCount(1);

    $this->artisan('forum:recount')->expectsOutputToContain('All counters were already right');
});
