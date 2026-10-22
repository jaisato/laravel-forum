<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ThreadReplied;
use Illuminate\Notifications\DatabaseNotification;

it('stores a self-contained payload for the notifications page', function () {
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    $replier = User::factory()->create(['name' => 'Ana López', 'username' => 'ana']);

    $reply = forumReply($thread, $replier, 'Prueba con **esto**');

    $notification = DatabaseNotification::query()->sole();

    expect($notification->type)->toBe('thread_replied')
        ->and($notification->notifiable->is($starter))->toBeTrue()
        ->and($notification->data)->toBe([
            'type' => 'thread_replied',
            'title' => 'Ana López replied to "A thread about testing the forum"',
            'excerpt' => 'Prueba con esto',
            'url' => url("/t/{$thread->slug}?post={$reply->id}#post-{$reply->id}"),
            'actor' => ['username' => 'ana', 'avatar_url' => null],
        ]);
});

it('writes notifications in the language of the recipient', function () {
    $starter = User::factory()->create(['locale' => 'es']);
    $thread = forumThread($starter);

    forumReply($thread, User::factory()->create(['name' => 'Ana']));

    expect(DatabaseNotification::query()->sole()->data['title'])->toBe('Ana respondió en «A thread about testing the forum»');
});

it('emails only members who asked for it and verified their address', function (array $preferences, bool $verified, array $channels) {
    $user = User::factory()->create(['preferences' => $preferences, 'email_verified_at' => $verified ? now() : null]);
    $reply = forumReply(forumThread());

    expect((new ThreadReplied($reply))->via($user))->toBe($channels);
})->with([
    'wants email' => [['email_replies' => true], true, ['database', 'mail']],
    'opted out' => [['email_replies' => false], true, ['database']],
    'unverified address' => [['email_replies' => true], false, ['database']],
]);
