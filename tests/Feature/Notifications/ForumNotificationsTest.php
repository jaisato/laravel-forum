<?php

declare(strict_types=1);

use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\UpdatePost;
use App\Actions\Messaging\LeaveConversation;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Enums\PostStatus;
use App\Events\PostCreated;
use App\Listeners\NotifyThreadSubscribers;
use App\Models\User;
use App\Notifications\NewPrivateMessage;
use App\Notifications\PostModerated;
use App\Notifications\SolutionAccepted;
use App\Notifications\ThreadReplied;
use App\Notifications\UserMentioned;
use App\Services\ThreadSubscriptions;
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

it('does not notify about posts that are no longer public', function () {
    Notification::fake();
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    $reply = forumReply($thread);
    Notification::fake();
    app(HidePost::class)->handle($reply, null);

    app(NotifyThreadSubscribers::class)->handle(new PostCreated($reply));

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
