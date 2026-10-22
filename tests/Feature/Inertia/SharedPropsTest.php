<?php

declare(strict_types=1);

use App\Actions\Messaging\StartConversation;
use App\Models\User;
use App\Notifications\ThreadReplied;
use Inertia\Testing\AssertableInertia as Assert;

it('shares no user with guests', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', null)
        ->where('locales', ['es', 'en'])
        ->has('locale')
        ->where('name', config('app.name')));
});

it('shares the signed in user with their unread counters', function () {
    $user = User::factory()->create(['name' => 'Ana García', 'username' => 'ana']);
    $reply = forumReply(forumThread($user));
    forumReply($reply->thread);
    $user->notifications()->first()->markAsRead();
    app(StartConversation::class)->handle(User::factory()->create(), collect([$user]), 'Hola', 'Primer mensaje');

    $this->actingAs($user)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user', [
            'id' => $user->id,
            'name' => 'Ana García',
            'username' => 'ana',
            'email' => $user->email,
            'avatar_url' => null,
            'role' => 'member',
            'is_staff' => false,
            'is_banned' => false,
            'email_verified' => true,
            // One unread reply (of two) plus the private message notification.
            'unread_notifications' => 2,
            'unread_conversations' => 1,
        ]));

    expect($user->notifications()->where('type', (new ThreadReplied($reply))->type())->count())->toBe(2);
});

it('tells the interface which AI features are on', function (bool $enabled, array $expected) {
    config([
        'forum.ai.enabled' => $enabled,
        'forum.ai.summaries.enabled' => true,
        'forum.ai.similar.enabled' => false,
        'forum.ai.tags.enabled' => true,
    ]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('features.ai', $expected));
})->with([
    'switched on' => [true, ['summaries' => true, 'similar' => false, 'tags' => true]],
    'switched off' => [false, ['summaries' => false, 'similar' => false, 'tags' => false]],
]);

it('marks staff and banned members', function () {
    $moderator = User::factory()->moderator()->banned()->create();

    $this->actingAs($moderator)->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.role', 'moderator')
        ->where('auth.user.is_staff', true)
        ->where('auth.user.is_banned', true));
});
