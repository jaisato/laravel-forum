<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\RestorePost;
use App\Actions\Forum\RestoreThread;
use App\Enums\ModerationActionType;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Tag;
use App\Models\User;

/**
 * The counters a delete and restore round trip must leave untouched.
 *
 * @return array<string, int|null>
 */
function forumCounters(Category $category, User ...$users): array
{
    $category->refresh();

    return [
        'category.threads' => $category->threads_count,
        'category.posts' => $category->posts_count,
        ...collect($users)->flatMap(fn (User $user): array => [
            "{$user->username}.threads" => $user->fresh()->threads_count,
            "{$user->username}.posts" => $user->fresh()->posts_count,
        ])->all(),
    ];
}

it('puts a restored reply back into every counter and as the latest post', function () {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);
    $this->travel(1)->hour();
    $reply = forumReply($thread);
    $before = forumCounters($category, $thread->author, $reply->author);
    $moderator = User::factory()->moderator()->create();

    app(DeletePost::class)->handle($reply, $moderator);
    app(RestorePost::class)->handle($reply, $moderator);

    expect(forumCounters($category, $thread->author, $reply->author))->toBe($before)
        ->and($reply->fresh()->trashed())->toBeFalse()
        ->and($thread->fresh()->replies_count)->toBe(1)
        ->and($thread->fresh()->last_post_id)->toBe($reply->id);
});

it('puts a restored thread back into every counter', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $thread = forumThread(category: $category, tagIds: [$tag->id]);
    $reply = forumReply($thread);
    $before = forumCounters($category, $thread->author, $reply->author);
    $moderator = User::factory()->moderator()->create();

    app(DeleteThread::class)->handle($thread, $moderator);
    app(RestoreThread::class)->handle($thread, $moderator);

    expect(forumCounters($category, $thread->author, $reply->author))->toBe($before)
        ->and($thread->fresh()->trashed())->toBeFalse()
        ->and($tag->fresh()->threads_count)->toBe(1);
});

it('does not count a restore twice', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();

    app(DeletePost::class)->handle($reply, $moderator);
    app(RestorePost::class)->handle($reply, $moderator);
    app(RestorePost::class)->handle($reply, $moderator);

    expect($thread->fresh()->replies_count)->toBe(1)
        ->and($reply->author->fresh()->posts_count)->toBe(1);
});

it('logs restores made by staff', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();

    app(DeletePost::class)->handle($reply, $moderator);
    app(RestorePost::class)->handle($reply, $moderator);
    app(DeleteThread::class)->handle($thread, $moderator);
    app(RestoreThread::class)->handle($thread, $moderator);

    expect(ModerationAction::query()->oldest('id')->pluck('action')->all())->toBe([
        ModerationActionType::PostDeleted,
        ModerationActionType::PostRestored,
        ModerationActionType::ThreadDeleted,
        ModerationActionType::ThreadRestored,
    ]);
});
