<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Actions\Users\DeleteAccount;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('the profile', function () {
    it('shows what a member shares in public, never their email', function () {
        $ana = User::factory()->create([
            'name' => 'Ana López',
            'username' => 'ana',
            'bio' => 'Desarrolladora PHP en Sevilla.',
            'location' => 'Sevilla',
            'website' => 'https://ana.example.com',
            'reputation' => 42,
            'created_at' => '2026-01-15 10:00:00',
            'last_seen_at' => '2026-10-20 18:30:00',
        ]);

        $this->get('/u/ana')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/Show')
                ->where('profile', [
                    'id' => $ana->id,
                    'name' => 'Ana López',
                    'username' => 'ana',
                    'avatar_url' => null,
                    'role' => 'member',
                    'bio' => 'Desarrolladora PHP en Sevilla.',
                    'location' => 'Sevilla',
                    'website' => 'https://ana.example.com',
                    'reputation' => 42,
                    'threads_count' => 0,
                    'posts_count' => 0,
                    'created_at' => '2026-01-15T10:00:00+00:00',
                    'last_seen_at' => '2026-10-20T18:30:00+00:00',
                ])
                ->where('tab', 'threads')
                ->where('meta.title', 'Ana López (@ana)')
                ->where('meta.description', 'Desarrolladora PHP en Sevilla.'));
    });

    it('only links web addresses', function (string $website) {
        User::factory()->create(['username' => 'ana', 'website' => $website]);

        $this->get('/u/ana')->assertInertia(fn (Assert $page) => $page->where('profile.website', null));
    })->with([
        'a script' => ['javascript:alert(document.cookie)'],
        'data' => ['data:text/html,<script>alert(1)</script>'],
        'no scheme' => ['ana.example.com'],
    ]);

    it('describes members who wrote no biography', function () {
        User::factory()->create(['name' => 'Luis', 'username' => 'luis', 'bio' => null]);

        $this->get('/u/luis')->assertInertia(fn (Assert $page) => $page
            ->where('meta.description', 'Luis is a member of '.config('app.name').'.'));
    });

    it('offers to write to the member those who may', function (?Closure $viewer, bool $expected) {
        $ana = User::factory()->create(['username' => 'ana']);
        $user = $viewer === null ? null : $viewer($ana);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->get('/u/ana')->assertInertia(fn (Assert $page) => $page->where('can.message', $expected));
    })->with([
        'a guest' => [null, false],
        'a member' => [fn () => User::factory()->create(), true],
        'the member themselves' => [fn (User $ana) => $ana, false],
        'a banned member' => [fn () => User::factory()->banned()->create(), false],
    ]);

    it('does not know erased accounts', function () {
        $ana = User::factory()->create(['username' => 'ana']);

        app(DeleteAccount::class)->handle($ana);

        $this->get('/u/ana')->assertNotFound();
        $this->get("/u/deleted-{$ana->id}")->assertNotFound();
    });

    it('does not know usernames nobody has', function () {
        $this->get('/u/nadie')->assertNotFound();
    });
});

describe('the tabs', function () {
    it('lists the threads the member started, newest first', function () {
        $ana = User::factory()->create(['username' => 'ana']);
        $older = forumThread($ana);
        $newer = forumThread($ana);
        $newer->forceFill(['created_at' => now()->addMinute()])->save();
        forumThread();

        $this->get('/u/ana')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'threads')
                ->has('threads.data', 2)
                ->where('threads.data.0.id', $newer->id)
                ->where('threads.data.1.id', $older->id)
                ->missing('posts'));
    });

    it('lists the replies of the member, not the posts that open their threads', function () {
        $ana = User::factory()->create(['username' => 'ana']);
        forumThread($ana);
        $thread = forumThread();
        $reply = forumReply($thread, $ana, 'Usa **Queue::fake()** en el test.');

        $this->get('/u/ana?tab=posts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'posts')
                ->has('posts.data', 1)
                ->where('posts.data.0', [
                    'id' => $reply->id,
                    'excerpt' => 'Usa Queue::fake() en el test.',
                    'status' => 'published',
                    'created_at' => $reply->created_at->toIso8601String(),
                    'author' => [
                        'id' => $ana->id,
                        'name' => $ana->name,
                        'username' => 'ana',
                        'avatar_url' => null,
                        'role' => 'member',
                    ],
                    'thread' => [
                        'id' => $thread->id,
                        'title' => $thread->title,
                        'slug' => $thread->slug,
                    ],
                ])
                ->missing('threads'));
    });

    it('falls back to the threads for a tab it does not know', function () {
        User::factory()->create(['username' => 'ana']);

        $this->get('/u/ana?tab=secrets')->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'threads')
            ->has('threads'));
    });

    it('gives search engines the replies tab as a page of its own', function (string $query, string $canonical) {
        User::factory()->create(['username' => 'ana']);

        $this->get('/u/ana'.$query)->assertInertia(fn (Assert $page) => $page->where('canonical', url($canonical)));
    })->with([
        'the threads tab' => ['', '/u/ana'],
        'the replies tab' => ['?tab=posts', '/u/ana?tab=posts'],
        'a later page of replies' => ['?tab=posts&page=2&sort=x', '/u/ana?tab=posts&page=2'],
        'a tab it does not know' => ['?tab=bogus', '/u/ana'],
    ]);

    it('keeps the tab in the page links', function () {
        config(['forum.pagination.posts' => 1]);
        $ana = User::factory()->create(['username' => 'ana']);
        $thread = forumThread();
        forumReply($thread, $ana);
        forumReply($thread, $ana);

        $this->get('/u/ana?tab=posts')->assertInertia(fn (Assert $page) => $page
            ->where('posts.links.next', url('/u/ana?tab=posts&page=2')));
    });

    it('shows nobody content they may not see', function (string $viewer, array $threads, array $replies) {
        $ana = User::factory()->create(['username' => 'ana']);
        $moderator = User::factory()->moderator()->create();
        $content = profileContent($ana, $moderator);

        $user = match ($viewer) {
            'guest' => null,
            'member' => User::factory()->create(),
            'the member' => $ana,
            'a moderator' => $moderator,
        };

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->get('/u/ana')->assertInertia(fn (Assert $page) => $page
            ->where('threads.data', fn ($shown) => collect($shown)->pluck('id')->sort()->values()->all()
                === collect($threads)->map(fn (string $name): int => $content[$name]->id)->sort()->values()->all()));

        $this->get('/u/ana?tab=posts')->assertInertia(fn (Assert $page) => $page
            ->where('posts.data', fn ($shown) => collect($shown)->pluck('id')->sort()->values()->all()
                === collect($replies)->map(fn (string $name): int => $content[$name]->id)->sort()->values()->all()));
    })->with([
        'a guest' => ['guest', ['published'], ['reply']],
        'another member' => ['member', ['published'], ['reply']],
        // Their own held content, as on the thread pages, but not what the moderators hid.
        'the member' => ['the member', ['published', 'held'], ['reply', 'held reply']],
        'a moderator' => ['a moderator', ['published', 'held', 'hidden'], ['reply', 'held reply', 'hidden reply', 'reply in a hidden thread']],
    ]);
});

/**
 * Everything a member may have posted: public, held for review, hidden by a
 * moderator and deleted, as threads and as replies.
 *
 * @return array<string, Thread|Post>
 */
function profileContent(User $member, User $moderator): array
{
    $published = forumThread($member);
    $held = forumThread($member);
    $hidden = forumThread($member);
    $deleted = forumThread($member);
    app(HidePost::class)->handle($held->firstPost, null);
    app(HidePost::class)->handle($hidden->firstPost, $moderator);
    app(DeleteThread::class)->handle($deleted, $moderator);

    $elsewhere = forumThread();
    $reply = forumReply($elsewhere, $member);
    $heldReply = forumReply($elsewhere, $member);
    $hiddenReply = forumReply($elsewhere, $member);
    $deletedReply = forumReply($elsewhere, $member);
    app(HidePost::class)->handle($heldReply, null);
    app(HidePost::class)->handle($hiddenReply, $moderator);
    app(DeletePost::class)->handle($deletedReply, $member);

    $hiddenThread = forumThread();
    $replyInHiddenThread = forumReply($hiddenThread, $member);
    app(HidePost::class)->handle($hiddenThread->firstPost, $moderator);

    $deletedThread = forumThread();
    forumReply($deletedThread, $member);
    app(DeleteThread::class)->handle($deletedThread, $moderator);

    return [
        'published' => $published,
        'held' => $held,
        'hidden' => $hidden,
        'deleted' => $deleted,
        'reply' => $reply,
        'held reply' => $heldReply,
        'hidden reply' => $hiddenReply,
        'deleted reply' => $deletedReply,
        'reply in a hidden thread' => $replyInHiddenThread,
    ];
}
