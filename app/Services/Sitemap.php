<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Thread;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml (spec §4.2): the home page, every category and the most
 * recently active public threads, built at most once an hour and forgotten
 * as soon as one of those threads leaves public view (ForgetSitemap).
 */
final class Sitemap
{
    private const string CACHE_KEY = 'forum.sitemap';

    public function xml(): string
    {
        return Cache::remember(self::CACHE_KEY, (int) config('forum.sitemap.cache_seconds'), fn (): string => view('sitemap', [
            'categories' => Category::query()->ordered()->get(['slug']),
            'threads' => Thread::query()
                ->visibleTo(null)
                ->orderByDesc('last_posted_at')
                ->orderByDesc('id')
                ->limit((int) config('forum.sitemap.threads'))
                ->get(['id', 'slug', 'last_posted_at']),
        ])->render());
    }

    /**
     * The next request builds it again.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
