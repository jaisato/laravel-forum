<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\ReactToPost;
use App\Actions\Moderation\HidePost;
use App\Enums\ReactionType;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;

/**
 * Every counter recount owns, read straight from the database.
 *
 * @return array<string, mixed>
 */
function counterSnapshot(): array
{
    return [
        'threads' => Thread::withTrashed()->orderBy('id')->get(['id', 'replies_count', 'last_post_id', 'last_posted_at'])->toArray(),
        'categories' => Category::query()->orderBy('id')->get(['id', 'threads_count', 'posts_count'])->toArray(),
        'tags' => Tag::query()->orderBy('id')->get(['id', 'threads_count'])->toArray(),
        'users' => User::withTrashed()->orderBy('id')->get(['id', 'threads_count', 'posts_count', 'reputation'])->toArray(),
    ];
}

/**
 * A small forum with every case the counters care about: replies, a hidden
 * reply, a deleted reply, a hidden thread, reactions and a solution.
 */
function forumWithHistory(): void
{
    $tag = Tag::factory()->create();
    $category = Category::factory()->create();
    $moderator = User::factory()->moderator()->create();
    $thread = forumThread(category: $category, tagIds: [$tag->id]);
    $answer = forumReply($thread);
    forumReply($thread, $answer->author);
    app(HidePost::class)->handle(forumReply($thread), $moderator);
    app(DeletePost::class)->handle(forumReply($thread), $moderator);
    app(ReactToPost::class)->add($answer, User::factory()->create(), ReactionType::Like);
    app(ReactToPost::class)->add($answer, User::factory()->create(), ReactionType::Love);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    $hiddenThread = forumThread(category: $category, tagIds: [$tag->id]);
    forumReply($hiddenThread);
    app(HidePost::class)->handle($hiddenThread->firstPost, $moderator);
}

it('puts back counters and reputation that drifted', function () {
    forumWithHistory();
    $expected = counterSnapshot();

    Thread::query()->toBase()->update(['replies_count' => 42, 'last_post_id' => null, 'last_posted_at' => null]);
    Category::query()->toBase()->update(['threads_count' => 0, 'posts_count' => 99]);
    Tag::query()->toBase()->update(['threads_count' => 7]);
    User::withTrashed()->toBase()->update(['threads_count' => 3, 'posts_count' => 0, 'reputation' => -5]);

    $this->artisan('forum:recount')->assertSuccessful();

    expect(counterSnapshot())->toBe($expected);
});

it('leaves a consistent forum untouched and says so', function () {
    forumWithHistory();
    $expected = counterSnapshot();

    $this->artisan('forum:recount')
        ->expectsOutputToContain('All counters were already right')
        ->assertSuccessful();

    expect(counterSnapshot())->toBe($expected);
});
