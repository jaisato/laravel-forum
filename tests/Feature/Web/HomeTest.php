<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page for guests and members', function (?string $role) {
    $user = forumUser($role ?? 'guest');

    ($user === null ? $this : $this->actingAs($user))
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('categories')
            ->has('threads')
            ->has('stats'));
})->with(['guest' => null, 'member' => 'member']);

it('shows the categories in order with their counters and latest public thread', function () {
    $general = Category::factory()->create(['name' => 'General', 'position' => 1]);
    $help = Category::factory()->create(['name' => 'Ayuda', 'position' => 0]);
    $this->travelTo(now()->subDay(), fn () => forumThread(category: $general));
    $latest = forumThread(category: $general);
    forumReply($latest, $replier = User::factory()->create());
    // Newer, but never the latest thread of anybody's home page.
    Thread::factory()->hidden()->for($general)->create();
    $deleted = forumThread(category: $general);
    app(DeleteThread::class)->handle($deleted, $deleted->author);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('categories', 2)
        ->where('categories.0.slug', $help->slug)
        ->where('categories.0.latest_thread', null)
        ->where('categories.1.slug', $general->slug)
        ->where('categories.1.threads_count', 2)
        ->where('categories.1.posts_count', 3)
        ->where('categories.1.latest_thread.id', $latest->id)
        ->where('categories.1.latest_thread.slug', $latest->slug)
        ->where('categories.1.latest_thread.last_poster.username', $replier->username)
        ->where('categories.1.latest_thread.last_poster', fn ($poster) => ! $poster->has('email')));
});

it('finds each category’s latest thread without aggregating all of its threads', function () {
    Category::factory()->count(3)->create()->each(fn (Category $category) => forumThread(category: $category));
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $this->get(route('home'))->assertOk();

    // One index lookup per category, however many threads it holds.
    expect(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'group by') && str_contains($sql, 'threads')))
        ->toBe([]);
});

it('shows the ten latest public threads', function () {
    $threads = collect(range(1, 12))
        ->map(fn (int $minutes) => $this->travelTo(now()->subMinutes(30 - $minutes), fn () => forumThread()));
    Thread::factory()->hidden()->create();

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('threads', 10)
        ->where('threads.0.id', $threads->last()->id)
        ->where('threads.9.id', $threads->get(2)->id));
});

it('shows the forum statistics', function () {
    forumReply(forumThread());
    $member = User::factory()->create(['last_seen_at' => now()]);

    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('stats.members', 3)
        ->where('stats.threads', 1)
        ->where('stats.posts', 2)
        ->where('stats.newest_member.username', $member->username)
        ->where('stats.online_count', 1)
        ->where('stats.online.0.username', $member->username));
});
