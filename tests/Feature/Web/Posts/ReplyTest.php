<?php

declare(strict_types=1);

use App\Actions\Moderation\LockThread;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

it('sends guests to the login page', function () {
    $thread = forumThread();

    $this->post(route('posts.store', $thread), ['body' => 'Hola a todos.'])
        ->assertRedirect(route('login'));
});

it('publishes a reply and opens the thread at it', function () {
    $thread = forumThread();
    $member = User::factory()->create();

    $response = $this->actingAs($member)->post(route('posts.store', $thread), ['body' => 'Mi **respuesta**.']);

    $reply = Post::query()->latest('id')->firstOrFail();

    $response->assertRedirect(route('threads.show', [$thread, 'post' => $reply->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Reply posted.']);

    expect($reply->thread_id)->toBe($thread->id)
        ->and($reply->user_id)->toBe($member->id)
        ->and($reply->body_html)->toContain('<strong>respuesta</strong>')
        ->and($thread->fresh()->replies_count)->toBe(1);
});

it('quotes a post of the same thread', function () {
    $thread = forumThread();
    $quoted = forumReply($thread);

    $this->actingAs(User::factory()->create())
        ->post(route('posts.store', $thread), ['body' => 'Estoy de acuerdo.', 'reply_to_id' => $quoted->id])
        ->assertSessionHasNoErrors();

    expect(Post::query()->latest('id')->value('reply_to_id'))->toBe($quoted->id);
});

it('validates the reply', function (array $data, string $field) {
    $thread = forumThread();
    $elsewhere = forumReply(forumThread());

    $data = array_map(fn (mixed $value) => $value === 'elsewhere' ? $elsewhere->id : $value, $data);

    $this->actingAs(User::factory()->create())
        ->from(route('threads.show', $thread))
        ->post(route('posts.store', $thread), $data)
        ->assertSessionHasErrors($field)
        ->assertRedirect(route('threads.show', $thread));

    expect($thread->fresh()->replies_count)->toBe(0);
})->with([
    'empty' => [['body' => ''], 'body'],
    'too short' => [['body' => 'a'], 'body'],
    'too long' => [['body' => str_repeat('a', 30001)], 'body'],
    'quoting another thread' => [['body' => 'Respuesta válida.', 'reply_to_id' => 'elsewhere'], 'reply_to_id'],
]);

it('refuses replies to a locked thread', function () {
    $thread = forumThread();
    app(LockThread::class)->handle($thread, User::factory()->moderator()->create());

    $this->actingAs(User::factory()->create())
        ->post(route('posts.store', $thread), ['body' => 'Respuesta válida.'])
        ->assertForbidden();
});

it('refuses a reply when the thread is locked while the request is handled', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    // Runs once the request has been authorised and validated.
    app()->afterResolving(StorePostRequest::class, fn () => app(LockThread::class)->handle(Thread::query()->findOrFail($thread->id), $moderator));

    $this->actingAs(User::factory()->create())
        ->post(route('posts.store', $thread), ['body' => 'Respuesta válida.'])
        ->assertForbidden();

    expect($thread->fresh())->is_locked->toBeTrue()->replies_count->toBe(0);
});

it('refuses replies from members who may not post', function (string $role) {
    $this->actingAs(forumUser($role))
        ->post(route('posts.store', forumThread()), ['body' => 'Respuesta válida.'])
        ->assertForbidden();
})->with(['unverified', 'banned']);

it('slows down members who reply too fast', function () {
    $thread = forumThread();
    $member = User::factory()->create();

    foreach (range(1, config('forum.rate_limits.replies.attempts')) as $i) {
        $this->actingAs($member)->post(route('posts.store', $thread), ['body' => "Respuesta número {$i}."]);
    }

    $this->actingAs($member)
        ->from(route('threads.show', $thread))
        ->post(route('posts.store', $thread), ['body' => 'Una de más.'])
        ->assertRedirect(route('threads.show', $thread))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Too many attempts. Please wait a moment.']);

    expect($thread->fresh()->replies_count)->toBe(config('forum.rate_limits.replies.attempts'));
});
