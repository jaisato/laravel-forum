<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * /robots.txt: crawlers may read the forum but not the pages behind an
 * account, the administration or the search results, and they find the
 * sitemap at the forum's own address. Outside production (staging, local)
 * nothing may be crawled.
 */
final class RobotsController extends Controller
{
    /**
     * Paths that are private, administrative or endless (searches).
     */
    private const array DISALLOWED = [
        '/admin',
        '/horizon',
        '/settings',
        '/notifications',
        '/messages',
        '/search',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->isProduction()) {
            foreach (self::DISALLOWED as $path) {
                $lines[] = "Disallow: {$path}";
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml';
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
