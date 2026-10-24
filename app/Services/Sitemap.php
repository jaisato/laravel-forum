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

    /**
     * Raised by forget(): a sitemap still being built from the threads as
     * they were before is stored under the previous version, which nobody
     * reads any more.
     */
    private const string VERSION_KEY = 'forum.sitemap.version';

    public function xml(): string
    {
        return Cache::remember($this->key(), (int) config('forum.sitemap.cache_seconds'), fn (): string => view('sitemap', [
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
        // The copy cached now goes at once, rather than waiting out its hour
        // under a key nobody reads any more.
        Cache::forget($this->key());
        Cache::increment(self::VERSION_KEY);
    }

    private function key(): string
    {
        return self::CACHE_KEY.':'.(int) Cache::get(self::VERSION_KEY, 0);
    }
}
