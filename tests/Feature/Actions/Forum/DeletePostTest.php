<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Moderation\HidePost;
use App\Enums\ModerationActionType;
use App\Events\PostDeleted;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

it('takes a deleted reply out of every counter', function () {
    $thread = forumThread();
    $author = User::factory()->create();
    forumReply($thread, $author);
    $reply = forumReply($thread, $author);

    app(DeletePost::class)->handle($reply, $author);

    expect($reply->fresh()->trashed())->toBeTrue()
        ->and($thread->fresh()->replies_count)->toBe(1)
        ->and($thread->category->fresh()->posts_count)->toBe(2)
        ->and($author->fresh()->posts_count)->toBe(1);
});

it('falls back to the previous post when the latest one is deleted', function () {
    $thread = forumThread();
    $earlier = forumReply($thread);
    $this->travel(1)->hour();
    $latest = forumReply($thread);

    app(DeletePost::class)->handle($latest, $latest->author);

    expect($thread->fresh())
        ->last_post_id->toBe($earlier->id)
        ->and($thread->fresh()->last_posted_at->equalTo($earlier->created_at))->toBeTrue();
});

it('does not count a deletion twice', function () {
    $thread = forumThread();
    $reply = forumReply($thread);

    app(DeletePost::class)->handle($reply, $reply->author);
    app(DeletePost::class)->handle($reply, $reply->author);

    expect($thread->fresh()->replies_count)->toBe(0)
        ->and($reply->author->fresh()->posts_count)->toBe(0);
});

it('removes the solution mark and its reputation with the post', function () {
    $thread = forumThread();
    $answer = forumReply($thread);
    $thread->forceFill(['solution_post_id' => $answer->id])->save();
    $answer->author->forceFill(['reputation' => 15])->save();

    app(DeletePost::class)->handle($answer, User::factory()->moderator()->create());

    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
});

it('deletes the whole thread with its opening post', function () {
    $thread = forumThread();

    app(DeletePost::class)->handle($thread->firstPost, $thread->author);

    expect($thread->fresh()->trashed())->toBeTrue();
});

it('logs deletions made by staff with their reason', function () {
    $own = forumReply(forumThread());
    $other = forumReply(forumThread());
    $moderator = User::factory()->moderator()->create();

    app(DeletePost::class)->handle($own, $own->author);
    app(DeletePost::class)->handle($other, $moderator, 'Off topic');

    $action = ModerationAction::query()->sole();

    expect($action->action)->toBe(ModerationActionType::PostDeleted)
        ->and($action->subject->is($other))->toBeTrue()
        ->and($action->reason)->toBe('Off topic');
});

it('announces the deletion once committed', function () {
    Event::fake([PostDeleted::class]);
    $reply = forumReply(forumThread());

    app(DeletePost::class)->handle($reply, $reply->author);

    Event::assertDispatched(PostDeleted::class, fn (PostDeleted $event): bool => $event->post->is($reply));
});

it('refuses the author once the reply was hidden after the request was authorised', function () {
    $reply = forumReply(forumThread());
    $authorised = Post::query()->findOrFail($reply->id);
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    expect(fn () => app(DeletePost::class)->handle($authorised, $reply->author))
        ->toThrow(AuthorizationException::class);
    expect($reply->fresh()->trashed())->toBeFalse();
});
