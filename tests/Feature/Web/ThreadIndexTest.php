<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\ReadTracker;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the public threads with the default sort and filter', function () {
    $older = $this->travelTo(now()->subHour(), fn () => forumThread());
    $newer = forumThread();
    Thread::factory()->hidden()->create();

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('threads/Index')
            ->where('context', null)
            ->where('filters', ['sort' => 'activity', 'filter' => 'all'])
            ->has('threads.data', 2)
            ->where('threads.data.0.id', $newer->id)
            ->where('threads.data.1.id', $older->id)
            ->where('threads.meta.total', 2));
});

it('describes every thread of the listing', function () {
    $author = User::factory()->create(['name' => 'Ana García', 'username' => 'ana']);
    $category = Category::factory()->create(['name' => 'Laravel', 'color' => '#f97316']);
    $tag = Tag::factory()->create(['name' => 'colas', 'color' => null]);
    $thread = forumThread($author, $category, 'Mis **colas** no procesan los *jobs* retrasados.', [$tag->id]);
    $replier = User::factory()->moderator()->create(['name' => 'Luis Pérez', 'username' => 'luis']);
    $reply = forumReply($thread, $replier);
    $thread->refresh();

    $this->get(route('threads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('threads.data.0', [
            'id' => $thread->id,
            'title' => $thread->title,
            'slug' => $thread->slug,
            'status' => 'published',
            'is_pinned' => false,
            'is_locked' => false,
            'is_solved' => false,
            'is_unread' => false,
            'replies_count' => 1,
            'views_count' => 0,
            'created_at' => $thread->created_at->toIso8601String(),
            'last_posted_at' => $reply->created_at->toIso8601String(),
            'excerpt' => 'Mis colas no procesan los jobs retrasados.',
            'author' => [
                'id' => $author->id,
                'name' => 'Ana García',
                'username' => 'ana',
                'avatar_url' => null,
                'role' => 'member',
            ],
            'last_poster' => [
                'id' => $replier->id,
                'name' => 'Luis Pérez',
                'username' => 'luis',
                'avatar_url' => null,
                'role' => 'moderator',
            ],
            'category' => [
                'id' => $category->id,
                'name' => 'Laravel',
                'slug' => $category->slug,
                'color' => '#f97316',
            ],
            'tags' => [
                ['id' => $tag->id, 'name' => 'colas', 'slug' => $tag->slug, 'color' => null],
            ],
        ]));
});

it('keeps the excerpt short', function () {
    forumThread(body: str_repeat('palabra ', 400));

    $excerpt = $this->get(route('threads.index'))->inertiaProps('threads.data.0.excerpt');

    expect(mb_strlen($excerpt))->toBeLessThanOrEqual(201)
        ->and($excerpt)->toEndWith('…');
});

it('presents the threads of erased accounts without an author', function () {
    $author = User::factory()->create();
    $thread = forumThread($author);
    forumReply($thread, $author);
    forumThread();
    $author->delete();

    $this->get(route('threads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('threads.data.1.id', $thread->id)
        ->where('threads.data.1.author', null)
        ->where('threads.data.1.last_poster', null));
});

it('applies the sort and filter of the query string and keeps them across pages', function () {
    config(['forum.pagination.threads' => 1]);
    $older = $this->travelTo(now()->subHour(), fn () => forumThread());
    $newer = forumThread();
    forumReply($older);
    forumReply(forumThread());

    $response = $this->get(route('threads.index', ['sort' => 'newest', 'filter' => 'unanswered']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['sort' => 'newest', 'filter' => 'unanswered'])
        ->where('threads.data.0.id', $newer->id)
        ->where('threads.meta.total', 1));

    $this->get(route('threads.index', ['sort' => 'replies']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.meta.last_page', 3)
            ->where('threads.links.next', fn (string $next) => str_contains($next, 'sort=replies') && str_contains($next, 'page=2')));
});

it('falls back to the defaults for unknown or personal values', function (array $query, ?string $role) {
    $user = forumUser($role ?? 'guest');

    ($user === null ? $this : $this->actingAs($user))
        ->get(route('threads.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['sort' => 'activity', 'filter' => 'all']));
})->with([
    'unknown sort and filter' => [['sort' => 'hot', 'filter' => 'trending'], 'member'],
    'arrays' => [['sort' => ['newest'], 'filter' => ['all']], 'member'],
    'personal filter for a guest' => [['filter' => 'mine'], null],
    'following for a guest' => [['filter' => 'following'], null],
]);

it('offers the personal filters to members', function () {
    $member = User::factory()->create();
    $own = forumThread($member);
    forumThread();

    $this->actingAs($member)
        ->get(route('threads.index', ['filter' => 'mine']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.filter', 'mine')
            ->has('threads.data', 1)
            ->where('threads.data.0.id', $own->id));
});

it('marks the threads with activity the member has not read as new', function () {
    $member = $this->travelTo(now()->subMonth(), fn () => User::factory()->create());
    $reads = app(ReadTracker::class);
    $old = $this->travelTo(now()->subYear(), fn () => forumThread());
    $read = $this->travelTo(now()->subDays(3), fn () => forumThread());
    $reads->markRead($read, $member);
    $answered = $this->travelTo(now()->subDays(2), fn () => forumThread());
    $reads->markRead($answered, $member);
    $this->travelTo(now()->subDay(), fn () => forumReply($answered));
    $unopened = $this->travelTo(now()->subHour(), fn () => forumThread());
    $own = forumThread($member);

    $unread = collect($this->actingAs($member)->get(route('threads.index'))->inertiaProps('threads.data'))
        ->mapWithKeys(fn (array $thread): array => [$thread['id'] => $thread['is_unread']]);

    expect($unread->all())->toBe([
        $own->id => false,
        $unopened->id => true,
        $answered->id => true,
        $read->id => false,
        $old->id => false,
    ]);
});

it('never marks threads as new for guests', function () {
    forumThread();

    $this->get(route('threads.index'))->assertInertia(fn (Assert $page) => $page
        ->where('threads.data.0.is_unread', false));
});

it('does not leak hidden threads to members but shows them to staff', function () {
    $public = forumThread();
    $hidden = Thread::factory()->hidden()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('threads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads.data', 1)
            ->where('threads.data.0.id', $public->id));

    $this->actingAs(User::factory()->moderator()->create())
        ->get(route('threads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('threads.data', 2)
            ->where('threads.data.0.id', $hidden->id)
            ->where('threads.data.0.status', 'hidden'));
});
