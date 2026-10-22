<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The community figures of the home page (spec §4.2). Thread and message
 * totals come from the denormalised category counters, so they only count
 * public content, and the snapshot is cached for a few minutes.
 */
final class ForumStats
{
    /**
     * Online members listed by name; online_count has all of them.
     */
    public const int ONLINE_LIMIT = 30;

    private const string CACHE_KEY = 'forum:stats';

    /**
     * @return array{members: int, threads: int, posts: int, newest_member: array<string, mixed>|null, online: list<array<string, mixed>>, online_count: int}
     */
    public function snapshot(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('forum.stats_cache_seconds'),
            fn (): array => $this->compute(),
        );
    }

    /**
     * @return array{members: int, threads: int, posts: int, newest_member: array<string, mixed>|null, online: list<array<string, mixed>>, online_count: int}
     */
    private function compute(): array
    {
        $newest = User::query()->inGoodStanding()->latest()->latest('id')->first();
        $online = User::query()->inGoodStanding()->online((int) config('forum.online_minutes'));

        return [
            'members' => User::query()->whereNotNull('email_verified_at')->count(),
            'threads' => (int) Category::query()->sum('threads_count'),
            'posts' => (int) Category::query()->sum('posts_count'),
            // Plain arrays: models do not belong in the cache.
            'newest_member' => $newest === null ? null : UserResource::make($newest)->resolve(),
            'online' => $online->clone()
                ->orderByDesc('last_seen_at')
                ->orderByDesc('id')
                ->limit(self::ONLINE_LIMIT)
                ->get()
                ->map(fn (User $user): array => UserResource::make($user)->resolve())
                ->values()
                ->all(),
            'online_count' => $online->count(),
        ];
    }
}
