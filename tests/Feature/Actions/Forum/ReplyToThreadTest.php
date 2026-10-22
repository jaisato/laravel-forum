<?php

declare(strict_types=1);

use App\Actions\Forum\ReplyToThread;
use App\Events\PostCreated;
use App\Models\ThreadRead;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('adds the reply as the latest post of the thread', function () {
    $thread = forumThread();
    $this->travel(5)->minutes();

    $reply = forumReply($thread, body: 'Gracias por la **pregunta**');
    $thread->refresh();

    expect($reply->thread_id)->toBe($thread->id)
        ->and($reply->body_html)->toContain('<strong>pregunta</strong>')
        ->and($thread->last_post_id)->toBe($reply->id)
        ->and($thread->last_posted_at->equalTo($reply->created_at))->toBeTrue()
        ->and($thread->first_post_id)->not->toBe($reply->id);
});

it('counts the reply in the thread, the category and its author', function () {
    $thread = forumThread();
    $author = User::factory()->create();

    forumReply($thread, $author);
    forumReply($thread, $author);

    expect($thread->fresh()->replies_count)->toBe(2)
        ->and($thread->category->fresh()->posts_count)->toBe(3)
        ->and($thread->category->fresh()->threads_count)->toBe(1)
        ->and($author->fresh()->posts_count)->toBe(2)
        ->and($author->fresh()->threads_count)->toBe(0);
});

it('subscribes the author of the reply and marks it read for them', function () {
    $thread = forumThread();
    $author = User::factory()->create();

    $reply = forumReply($thread, $author);

    expect($thread->subscribers()->whereKey($author->id)->exists())->toBeTrue()
        ->and(ThreadRead::query()->where('user_id', $author->id)->value('last_read_post_id'))->toBe($reply->id);
});

it('quotes a post of the same thread', function () {
    $thread = forumThread();

    $reply = app(ReplyToThread::class)->handle($thread, User::factory()->create(), 'Exacto', $thread->firstPost);

    expect($reply->replyTo->is($thread->firstPost))->toBeTrue();
});

it('refuses to quote a post from another thread', function () {
    $thread = forumThread();
    $elsewhere = forumThread();

    expect(fn () => app(ReplyToThread::class)->handle($thread, User::factory()->create(), 'Exacto', $elsewhere->firstPost))
        ->toThrow(InvalidArgumentException::class);
});

it('announces the reply once committed', function () {
    Event::fake([PostCreated::class]);
    $thread = forumThread();

    $reply = forumReply($thread);

    Event::assertDispatched(PostCreated::class, fn (PostCreated $event): bool => $event->post->is($reply));
});
