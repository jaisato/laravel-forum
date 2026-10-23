<?php

declare(strict_types=1);

use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use App\Events\PostModerated;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('hides a reply for staff only and takes it out of the counters', function () {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);
    $earlier = forumReply($thread);
    $this->travel(1)->minute();
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();

    app(HidePost::class)->handle($reply, $moderator, 'Spam');

    expect($reply->fresh()->status)->toBe(PostStatus::Hidden)
        ->and($thread->fresh())->replies_count->toBe(1)->last_post_id->toBe($earlier->id)
        ->and($category->fresh()->posts_count)->toBe(2)
        ->and($reply->author->fresh()->posts_count)->toBe(0)
        ->and(ModerationAction::query()->sole())
        ->action->toBe(ModerationActionType::PostHidden)
        ->moderator_id->toBe($moderator->id)
        ->reason->toBe('Spam');
});

it('holds a post for review when the system hides it', function () {
    $thread = forumThread();
    $reply = forumReply($thread);

    app(HidePost::class)->handle($reply, null, 'Flagged by the AI moderator');

    expect($reply->fresh()->status)->toBe(PostStatus::PendingReview)
        ->and($thread->fresh()->replies_count)->toBe(0)
        ->and(ModerationAction::query()->sole()->moderator_id)->toBeNull();
});

it('takes the whole thread out of sight when its opening post is held or hidden', function (?string $role, ThreadStatus $threadStatus, PostStatus $postStatus) {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);
    forumReply($thread);

    app(HidePost::class)->handle($thread->firstPost, $role === null ? null : forumUser($role));

    expect($thread->fresh()->status)->toBe($threadStatus)
        ->and($thread->firstPost->fresh()->status)->toBe($postStatus)
        ->and($category->fresh())->threads_count->toBe(0)->posts_count->toBe(0)
        ->and($thread->author->fresh())->threads_count->toBe(0)->posts_count->toBe(0);
})->with([
    'held by the system' => [null, ThreadStatus::PendingReview, PostStatus::PendingReview],
    'hidden by a moderator' => ['moderator', ThreadStatus::Hidden, PostStatus::Hidden],
]);

it('approves a reply back into the thread and the counters', function () {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();
    app(HidePost::class)->handle($reply, null);

    app(ApprovePost::class)->handle($reply, $moderator);

    expect($reply->fresh()->status)->toBe(PostStatus::Published)
        ->and($thread->fresh())->replies_count->toBe(1)->last_post_id->toBe($reply->id)
        ->and($category->fresh()->posts_count)->toBe(2)
        ->and(ModerationAction::query()->latest('id')->first()->action)->toBe(ModerationActionType::PostApproved);
});

it('publishes the thread again when its opening post is approved', function () {
    $category = Category::factory()->create();
    $thread = forumThread(category: $category);
    forumReply($thread);
    $moderator = User::factory()->moderator()->create();
    app(HidePost::class)->handle($thread->firstPost, $moderator);

    app(ApprovePost::class)->handle($thread->firstPost, $moderator);

    expect($thread->fresh()->status)->toBe(ThreadStatus::Published)
        ->and($category->fresh())->threads_count->toBe(1)->posts_count->toBe(2)
        ->and($thread->author->fresh()->threads_count)->toBe(1);
});

it('changes nothing when hiding or approving twice', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();

    app(HidePost::class)->handle($reply, $moderator);
    app(HidePost::class)->handle($reply, $moderator);
    app(ApprovePost::class)->handle($reply, $moderator);
    app(ApprovePost::class)->handle($reply, $moderator);

    expect($thread->fresh()->replies_count)->toBe(1)
        ->and(ModerationAction::query()->count())->toBe(2);
});

it('tells the author when their post is moderated', function () {
    Event::fake([PostModerated::class]);
    $reply = forumReply(forumThread());
    $moderator = User::factory()->moderator()->create();

    app(HidePost::class)->handle($reply, $moderator, 'Off topic');
    app(ApprovePost::class)->handle($reply, $moderator);

    Event::assertDispatched(PostModerated::class, fn (PostModerated $event): bool => $event->post->is($reply)
        && $event->status === PostStatus::Hidden
        && $event->reason === 'Off topic'
        && $event->moderator?->is($moderator) === true);
    Event::assertDispatched(PostModerated::class, fn (PostModerated $event): bool => $event->status === PostStatus::Published);
});
