<?php

declare(strict_types=1);

use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\ReplyToThread;
use App\Actions\Forum\UpdatePost;
use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Events\PostUpdated;
use App\Listeners\NotifyMentionedUsers;
use App\Listeners\NotifyThreadSubscribers;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\NewPrivateMessage;
use App\Notifications\PostModerated;
use App\Notifications\SolutionAccepted;
use App\Notifications\ThreadReplied;
use App\Notifications\UserMentioned;
use App\Services\ThreadSubscriptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('tells every subscriber but the author about a new reply', function () {
    Notification::fake();
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    $follower = User::factory()->create();
    app(ThreadSubscriptions::class)->subscribe($thread, $follower);
    $replier = User::factory()->create();

    $reply = forumReply($thread, $replier);

    Notification::assertSentTo([$starter, $follower], ThreadReplied::class, fn (ThreadReplied $notification): bool => $notification->post->is($reply));
    Notification::assertNotSentTo($replier, ThreadReplied::class);
});

it('mentions subscribers instead of telling them twice', function () {
    Notification::fake();
    $starter = User::factory()->create(['username' => 'starter']);
    $thread = forumThread($starter);

    forumReply($thread, body: 'Gracias @starter');

    Notification::assertSentTo($starter, UserMentioned::class);
    Notification::assertNotSentTo($starter, ThreadReplied::class);
});

it('tells mentioned members, but not the author', function () {
    Notification::fake();
    $ana = User::factory()->create(['username' => 'ana']);
    $author = User::factory()->create(['username' => 'autor']);

    forumThread($author, body: 'Hola @ana y @autor, y @nadie');

    Notification::assertSentTo($ana, UserMentioned::class);
    Notification::assertNotSentTo($author, UserMentioned::class);
    Notification::assertCount(1);
});

it('only tells the members newly mentioned in an edit', function () {
    $ana = User::factory()->create(['username' => 'ana']);
    $luis = User::factory()->create(['username' => 'luis']);
    $reply = forumReply(forumThread(), body: 'Hola @ana');
    Notification::fake();

    app(UpdatePost::class)->handle($reply, $reply->author, 'Hola @ana y @luis');

    Notification::assertSentTo($luis, UserMentioned::class);
    Notification::assertNotSentTo($ana, UserMentioned::class);
});

/**
 * Replies and edits the post with the events held back, then runs their
 * queued listeners: what an asynchronous queue does when the jobs start
 * after the edit.
 */
function replyThenEditBeforeTheJobsRun(Thread $thread, User $author, string $body, string $edited): void
{
    Event::fake([PostCreated::class, PostUpdated::class]);

    $reply = app(ReplyToThread::class)->handle($thread, $author, $body);
    app(UpdatePost::class)->handle($reply, $author, $edited);

    Event::dispatched(PostCreated::class)->each(function (array $arguments): void {
        app(NotifyThreadSubscribers::class)->handle($arguments[0]);
        app(NotifyMentionedUsers::class)->handlePostCreated($arguments[0]);
    });
    Event::dispatched(PostUpdated::class)->each(
        fn (array $arguments) => app(NotifyMentionedUsers::class)->handlePostUpdated($arguments[0]),
    );
}

it('notifies a mention once when the queued jobs run after an edit', function () {
    $bob = User::factory()->create(['username' => 'bob']);
    $starter = User::factory()->create(['username' => 'starter']);
    $thread = forumThread($starter);
    Notification::fake();

    replyThenEditBeforeTheJobsRun($thread, User::factory()->create(), 'Sin menciones todavía.', 'Ahora sí, @bob y @starter.');

    Notification::assertSentToTimes($bob, UserMentioned::class, 1);
    // The reply itself did not mention the starter: they hear of it as a
    // subscriber, and of the edit as a mention.
    Notification::assertSentToTimes($starter, ThreadReplied::class, 1);
    Notification::assertSentToTimes($starter, UserMentioned::class, 1);
});

it('does not notify a mention removed before its job runs', function () {
    $bob = User::factory()->create(['username' => 'bob']);
    $thread = forumThread();
    Notification::fake();

    replyThenEditBeforeTheJobsRun($thread, User::factory()->create(), 'Hola @bob.', 'Hola a todos.');

    Notification::assertNotSentTo($bob, UserMentioned::class);
});

it('does not notify about posts that are no longer public', function () {
    Notification::fake();
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    $reply = forumReply($thread);
    Notification::fake();
    app(HidePost::class)->handle($reply, null);

    app(NotifyThreadSubscribers::class)->handle(new PostCreated($reply, []));

    Notification::assertNotSentTo($starter, ThreadReplied::class);
});

it('drops a queued notification when the post was deleted in the meantime', function () {
    $starter = User::factory()->create();
    $reply = forumReply(forumThread($starter));
    $notification = new ThreadReplied($reply);

    expect($notification->shouldSend($starter, 'database'))->toBeTrue();

    $reply->delete();

    expect($notification->shouldSend($starter, 'database'))->toBeFalse();
});

it('tells authors when their answer becomes the solution, unless they chose it', function () {
    Notification::fake();
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    $answer = forumReply($thread);
    $ownAnswer = forumReply($thread, $starter);

    app(MarkSolution::class)->handle($thread, $answer, $starter);
    app(MarkSolution::class)->handle($thread, $ownAnswer, $starter);

    Notification::assertSentTo($answer->author, SolutionAccepted::class);
    Notification::assertNotSentTo($starter, SolutionAccepted::class);
});

it('tells authors when their post is moderated', function () {
    $reply = forumReply(forumThread());
    $moderator = User::factory()->moderator()->create();
    Notification::fake();

    app(HidePost::class)->handle($reply, $moderator, 'Off topic');
    app(ApprovePost::class)->handle($reply, $moderator);

    Notification::assertSentTo($reply->author, PostModerated::class, fn (PostModerated $notification): bool => $notification->status === PostStatus::Hidden
        && $notification->reason === 'Off topic');
    Notification::assertSentTo($reply->author, PostModerated::class, fn (PostModerated $notification): bool => $notification->status === PostStatus::Published);
});

it('does not tell moderators about what they do to their own posts', function () {
    $moderator = User::factory()->moderator()->create();
    $reply = forumReply(forumThread(), $moderator);
    Notification::fake();

    app(HidePost::class)->handle($reply, $moderator, 'Duplicado');

    Notification::assertNotSentTo($moderator, PostModerated::class);
});

it('tells the other active participants about a private message', function () {
    Notification::fake();
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $conversation = app(StartConversation::class)->handle($alice, collect([$bob, $carol]), 'Hola', 'Primer mensaje');
    app(LeaveConversation::class)->handle($conversation, $carol);

    app(SendMessage::class)->handle($conversation, $bob, 'Respuesta');

    Notification::assertSentToTimes($bob, NewPrivateMessage::class, 1);
    Notification::assertSentToTimes($carol, NewPrivateMessage::class, 1);
    Notification::assertSentToTimes($alice, NewPrivateMessage::class, 1);
});
