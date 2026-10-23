<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\RestorePost;
use App\Actions\Forum\UpdateThread;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\MoveThread;
use App\Models\Category;
use App\Models\Tag;
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
        if (preg_match('/^update "?users"? /i', $query->sql) === 1) {
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

/**
 * The updates a callback sends to a table and the ordered selects that lock
 * its rows first, in order.
 *
 * @return list<string>
 */
function statementsOn(string $table, Closure $callback): array
{
    $statements = [];
    DB::listen(function (QueryExecuted $query) use ($table, &$statements): void {
        if (preg_match('/^(select .* from "?'.$table.'"? .* order by|update "?'.$table.'"? )/i', $query->sql) === 1) {
            $statements[] = strtolower($query->sql);
        }
    });

    $callback();

    return $statements;
}

it('locks both categories in id order before moving a thread between them', function () {
    [$lower, $higher] = Category::factory()->count(2)->create()->sortBy('id')->values()->all();
    $thread = forumThread(category: $higher);

    $statements = statementsOn('categories', fn () => app(MoveThread::class)->handle($thread, $lower, User::factory()->moderator()->create()));

    // Two moderators moving threads both ways between the same categories
    // queue up instead of each holding the row the other needs.
    expect($statements)->toHaveCount(3)
        ->and($statements[0])->toContain('order by "id" asc')
        ->and($statements[1])->toStartWith('update')
        ->and($statements[2])->toStartWith('update');
});

it('locks the tags in id order before moving the counters of an edited thread', function () {
    [$first, $second] = Tag::factory()->count(2)->create()->sortBy('id')->values()->all();
    $thread = forumThread(tagIds: [$second->id]);

    $statements = statementsOn('tags', fn () => app(UpdateThread::class)->handle($thread, $thread->author, $thread->title, [$first->id]));

    expect($statements)->toHaveCount(3)
        ->and($statements[0])->toContain('order by "id" asc')
        ->and($statements[1])->toStartWith('update')
        ->and($statements[2])->toStartWith('update')
        ->and($first->fresh()->threads_count)->toBe(1)
        ->and($second->fresh()->threads_count)->toBe(0);
});
