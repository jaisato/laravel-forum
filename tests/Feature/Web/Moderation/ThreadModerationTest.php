<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\User;

it('pins, unpins, locks and unlocks a thread', function (string $route, array $expected, string $message) {
    $thread = forumThread();
    if (in_array($route, ['unpin', 'unlock'], true)) {
        $thread->forceFill(['is_pinned' => true, 'is_locked' => true])->save();
    }

    $this->actingAs(User::factory()->moderator()->create())
        ->from(route('threads.show', $thread))
        ->post(route("moderation.threads.{$route}", $thread))
        ->assertRedirect(route('threads.show', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $message]);

    expect($thread->fresh()->only(array_keys($expected)))->toBe($expected);
})->with([
    'pin' => ['pin', ['is_pinned' => true], 'Thread pinned.'],
    'unpin' => ['unpin', ['is_pinned' => false], 'Thread unpinned.'],
    'lock' => ['lock', ['is_locked' => true], 'Thread locked.'],
    'unlock' => ['unlock', ['is_locked' => false], 'Thread unlocked.'],
]);

it('moves a thread to another category', function () {
    $thread = forumThread();
    $target = Category::factory()->create();

    $this->actingAs(User::factory()->moderator()->create())
        ->post(route('moderation.threads.move', $thread), ['category_id' => $target->id])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread moved.']);

    expect($thread->fresh()->category_id)->toBe($target->id);
});

it('validates the target category', function () {
    $this->actingAs(User::factory()->moderator()->create())
        ->post(route('moderation.threads.move', forumThread()), ['category_id' => 999])
        ->assertSessionHasErrors('category_id');
});

it('restores a deleted thread', function () {
    $thread = forumThread();
    app(DeleteThread::class)->handle($thread, $thread->author);

    $this->actingAs(User::factory()->moderator()->create())
        ->post(route('moderation.threads.restore', $thread))
        ->assertRedirect(route('threads.show', $thread))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Thread restored.']);

    expect($thread->fresh()->trashed())->toBeFalse();
});

it('is for staff only', function (string $route) {
    $thread = forumThread();

    $this->actingAs(User::factory()->create())
        ->post(route("moderation.threads.{$route}", $thread), ['category_id' => Category::factory()->create()->id])
        ->assertForbidden();

    $this->actingAs(forumUser('banned moderator'))
        ->post(route("moderation.threads.{$route}", $thread), ['category_id' => Category::factory()->create()->id])
        ->assertForbidden();
})->with(['pin', 'unpin', 'lock', 'unlock', 'move']);

it('turns members away before looking the thread up, whether it exists or not', function (string $route) {
    $member = User::factory()->create();
    $hidden = forumThread();
    app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());
    $deleted = forumThread();
    app(DeleteThread::class)->handle($deleted, $deleted->author);

    // Hidden, deleted or never there: the same answer, so a member cannot
    // learn which titles exist (the slug comes from the title).
    foreach ([$hidden->slug, $deleted->slug, 'a-thread-nobody-wrote'] as $slug) {
        $this->actingAs($member)
            ->post("/moderation/threads/{$slug}/{$route}", ['category_id' => Category::factory()->create()->id])
            ->assertForbidden();
    }
})->with(['pin', 'unpin', 'lock', 'unlock', 'move', 'restore']);

it('sends guests to the login page', function () {
    $this->post(route('moderation.threads.pin', forumThread()))->assertRedirect(route('login'));
});
