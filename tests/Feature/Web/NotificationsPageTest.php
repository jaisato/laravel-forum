<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('is for members only', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    $this->getJson(route('notifications.unread'))->assertUnauthorized();
});

describe('the list', function () {
    it('shows the notifications of the member, newest first', function () {
        $member = User::factory()->create();
        $thread = forumThread($member);
        $ana = User::factory()->create(['name' => 'Ana López', 'username' => 'ana']);
        $this->travel(-5)->minutes();
        forumReply($thread, $ana, 'Prueba con **esto**');
        $this->travelBack();
        $latest = forumReply($thread, $ana, 'Otra respuesta');
        forumReply(forumThread());
        $notification = $member->notifications()->first();

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->has('notifications.data', 2)
                ->where('notifications.data.0', [
                    'id' => $notification->id,
                    'type' => 'thread_replied',
                    'title' => 'Ana López replied to "A thread about testing the forum"',
                    'excerpt' => 'Otra respuesta',
                    'url' => "/t/{$thread->slug}?post={$latest->id}#post-{$latest->id}",
                    'post_id' => $latest->id,
                    'actor' => ['username' => 'ana', 'avatar_url' => null],
                    'read_at' => null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ])
                ->where('notifications.data.1.excerpt', 'Prueba con esto'));
    });

    it('counts the unread ones for the bell', function () {
        $member = User::factory()->create();
        $thread = forumThread($member);
        forumReply($thread);
        forumReply($thread);
        $member->notifications()->first()->markAsRead();

        $this->actingAs($member)
            ->getJson(route('notifications.unread'))
            ->assertOk()
            ->assertExactJson(['count' => 1]);
    });
});

describe('reading', function () {
    it('opens the post a notification is about and marks it read', function () {
        $member = User::factory()->create();
        $thread = forumThread($member);
        $reply = forumReply($thread);
        $notification = $member->notifications()->sole();

        $this->actingAs($member)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect("/t/{$thread->slug}?post={$reply->id}#post-{$reply->id}");

        expect($notification->fresh()->read_at)->not->toBeNull();
    });

    it('keeps the anchor of the post on Inertia visits', function () {
        $member = User::factory()->create();
        $thread = forumThread($member);
        $reply = forumReply($thread);

        $this->actingAs($member)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('notifications.read', $member->notifications()->sole()->id))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Redirect', url("/t/{$thread->slug}?post={$reply->id}#post-{$reply->id}"));
    });

    it('stays on this forum, whatever address the notification holds', function (string $stored, string $opened) {
        $member = User::factory()->create();
        forumReply(forumThread($member));
        $notification = $member->notifications()->sole();
        $notification->forceFill(['data' => [...$notification->data, 'url' => $stored]])->save();

        $this->actingAs($member)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect($opened);
    })->with([
        // Notifications written before the forum moved to another address.
        'an old host' => ['https://old-forum.example.com/t/colas?post=3#post-3', '/t/colas?post=3#post-3'],
        'another site' => ['//evil.example.com/phishing', '/phishing'],
        'a doubled slash' => ['https://forum.example.com//evil.example.com/phishing', '/evil.example.com/phishing'],
        'no address' => ['', '/notifications'],
    ]);

    it('does not let members open the notifications of others', function () {
        $member = User::factory()->create();
        forumReply(forumThread($member));
        $notification = $member->notifications()->sole();

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        expect($notification->fresh()->read_at)->toBeNull();
    });

    it('does not know ids that are no notification', function () {
        $this->actingAs(User::factory()->create())
            ->post('/notifications/not-a-uuid/read')
            ->assertNotFound();
    });

    it('marks every notification of the member read', function () {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $thread = forumThread($member);
        forumReply($thread);
        forumReply($thread);
        forumReply(forumThread($other));

        $this->actingAs($member)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'All notifications marked as read.']);

        expect($member->unreadNotifications()->count())->toBe(0)
            ->and($other->unreadNotifications()->count())->toBe(1);
    });
});

describe('content that is no longer public', function () {
    it('withholds what the notification said and where it led', function (Closure $retract) {
        $member = User::factory()->create();
        $thread = forumThread($member);
        $reply = forumReply($thread, User::factory()->create(['name' => 'Spammer']), 'Compra relojes baratos');
        $notification = $member->notifications()->sole();

        $retract($reply, $thread, User::factory()->moderator()->create());

        // Found by id: hiding the thread hides the member's own opening post
        // too, whose notice may share the second this notification was sent.
        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where(
                'notifications.data',
                fn (Collection $notifications): bool => Arr::sortRecursive($notifications->firstWhere('id', $notification->id) ?? []) === Arr::sortRecursive([
                    'id' => $notification->id,
                    'type' => 'thread_replied',
                    'title' => 'This content is no longer available.',
                    'excerpt' => null,
                    'url' => null,
                    'post_id' => null,
                    'actor' => null,
                    'read_at' => null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ]),
            ));

        // Opening it only marks it read.
        $this->actingAs($member)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        expect($notification->fresh()->read_at)->not->toBeNull();
    })->with([
        'hidden' => [fn (Post $reply, Thread $thread, User $moderator) => app(HidePost::class)->handle($reply, $moderator)],
        'held for review' => [fn (Post $reply) => app(HidePost::class)->handle($reply, null)],
        'deleted' => [fn (Post $reply, Thread $thread, User $moderator) => app(DeletePost::class)->handle($reply, $moderator)],
        'in a hidden thread' => [fn (Post $reply, Thread $thread, User $moderator) => app(HidePost::class)->handle($thread->firstPost, $moderator)],
        'in a deleted thread' => [fn (Post $reply, Thread $thread, User $moderator) => app(DeleteThread::class)->handle($thread, $moderator)],
    ]);

    it('tells it again once the post is approved', function () {
        $member = User::factory()->create();
        $thread = forumThread($member);
        $reply = forumReply($thread, body: 'Una respuesta retenida');
        app(HidePost::class)->handle($reply, null);
        app(ApprovePost::class)->handle($reply, User::factory()->moderator()->create());

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.excerpt', 'Una respuesta retenida'));
    });

    it('still tells staff, and the author about their own post', function () {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $thread = forumThread($moderator);
        $reply = forumReply($thread, $author, 'Compra relojes baratos');
        app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

        $this->actingAs($moderator)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.type', 'thread_replied')
                ->where('notifications.data.0.excerpt', 'Compra relojes baratos'));

        $this->actingAs($author)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.type', 'post_moderated')
                ->where('notifications.data.0.title', 'Your post in "A thread about testing the forum" was hidden by the moderators')
                ->where('notifications.data.0.url', "/t/{$thread->slug}?post={$reply->id}#post-{$reply->id}"));
    });
});
