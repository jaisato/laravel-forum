<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a public thread by its slug', function () {
    $thread = forumThread();

    $this->get(route('threads.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('threads/Show')
            ->where('thread.id', $thread->id)
            ->where('thread.title', $thread->title)
            // Embedded resources arrive as plain objects, not wrapped in "data".
            ->where('thread.author.username', $thread->author->username));

    expect(route('threads.show', $thread))->toEndWith('/t/'.$thread->slug);
});

it('answers 404 instead of revealing hidden threads', function (string $viewer) {
    $thread = Thread::factory()->hidden()->create();
    $user = match ($viewer) {
        'guest' => null,
        'member' => User::factory()->create(),
        // A moderator hid it: its author no longer sees it either.
        'author' => $thread->author,
    };

    ($user === null ? $this : $this->actingAs($user))
        ->get(route('threads.show', $thread))
        ->assertNotFound();
})->with(['guest', 'member', 'author']);

it('shows hidden threads to staff and pending threads to their author', function () {
    $hidden = Thread::factory()->hidden()->create();
    $pending = Thread::factory()->pending()->create();

    $this->actingAs(User::factory()->moderator()->create())
        ->get(route('threads.show', $hidden))
        ->assertOk();

    $this->actingAs($pending->author)
        ->get(route('threads.show', $pending))
        ->assertOk();
});
