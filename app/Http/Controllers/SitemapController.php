<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Thread;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /sitemap.xml (spec §4.2): the home page, every category and the most
 * recently active public threads, built at most once an hour.
 */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('forum.sitemap', (int) config('forum.sitemap.cache_seconds'), fn (): string => view('sitemap', [
            'categories' => Category::query()->ordered()->get(['slug']),
            'threads' => Thread::query()
                ->visibleTo(null)
                ->orderByDesc('last_posted_at')
                ->orderByDesc('id')
                ->limit((int) config('forum.sitemap.threads'))
                ->get(['id', 'slug', 'last_posted_at']),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
