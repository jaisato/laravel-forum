<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;
use App\Services\ForumStats;

it('counts the community from the denormalised counters', function () {
    forumReply(forumThread());
    // Not public, so neither counted nor shown.
    Thread::factory()->hidden()->create();
    User::factory()->unverified()->create();
    User::factory()->create()->delete();

    expect(app(ForumStats::class)->snapshot())
        // The thread author, the replier and the hidden thread's author.
        ->members->toBe(3)
        ->threads->toBe(1)
        // The opening post counts as a message.
        ->posts->toBe(2);
});

it('presents the newest member in good standing', function () {
    $this->travelTo(now()->subDays(3), fn () => User::factory()->create());
    $newest = $this->travelTo(now()->subDay(), fn () => User::factory()->create([
        'name' => 'Nueva Socia',
        'username' => 'nueva',
    ]));
    User::factory()->unverified()->create();
    User::factory()->banned()->create();
    User::factory()->create()->delete();

    expect(app(ForumStats::class)->snapshot()['newest_member'])->toBe([
        'id' => $newest->id,
        'name' => 'Nueva Socia',
        'username' => 'nueva',
        'avatar_url' => null,
        'role' => 'member',
    ]);
});

it('has no newest member in an empty forum', function () {
    expect(app(ForumStats::class)->snapshot())
        ->members->toBe(0)
        ->newest_member->toBeNull()
        ->online->toBe([])
        ->online_count->toBe(0);
});

it('lists the members seen in the last fifteen minutes', function () {
    $recent = User::factory()->create(['last_seen_at' => now()->subMinutes(2)]);
    $earlier = User::factory()->create(['last_seen_at' => now()->subMinutes(14)]);
    $lapsedBan = User::factory()->banned(now()->subDay())->create(['last_seen_at' => now()->subMinutes(5)]);
    User::factory()->create(['last_seen_at' => now()->subMinutes(16)]);
    User::factory()->create(['last_seen_at' => null]);
    User::factory()->banned()->create(['last_seen_at' => now()]);

    $stats = app(ForumStats::class)->snapshot();

    expect(array_column($stats['online'], 'username'))
        ->toBe([$recent->username, $lapsedBan->username, $earlier->username])
        ->and($stats['online'][0])->toHaveKeys(['id', 'name', 'username', 'avatar_url', 'role'])
        ->and($stats['online'][0])->not->toHaveKey('email')
        ->and($stats['online_count'])->toBe(3);
});

it('shows a bounded number of online members but counts them all', function () {
    User::factory()->count(ForumStats::ONLINE_LIMIT + 2)->create(['last_seen_at' => now()]);

    $stats = app(ForumStats::class)->snapshot();

    expect($stats['online'])->toHaveCount(ForumStats::ONLINE_LIMIT)
        ->and($stats['online_count'])->toBe(ForumStats::ONLINE_LIMIT + 2);
});

it('caches the snapshot for five minutes', function () {
    $stats = app(ForumStats::class);

    expect($stats->snapshot()['members'])->toBe(0);

    User::factory()->create();

    expect($stats->snapshot()['members'])->toBe(0);

    $this->travel(config('forum.stats_cache_seconds') + 1)->seconds();

    expect($stats->snapshot()['members'])->toBe(1);
});
