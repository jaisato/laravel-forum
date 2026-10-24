<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PostStatus;
use App\Events\PostModerated;
use App\Events\ThreadDeleted;
use App\Services\Sitemap;

/**
 * The sitemap lists public threads and is built at most once an hour
 * (Sitemap): it is forgotten the moment one of them leaves public view (its
 * opening post hidden or held, or the thread deleted), so search engines are
 * never sent to it.
 */
final class ForgetSitemap
{
    public function __construct(private readonly Sitemap $sitemap) {}

    public function handle(PostModerated|ThreadDeleted $event): void
    {
        if ($event instanceof PostModerated && ($event->status === PostStatus::Published || ! $event->post->isFirst())) {
            return;
        }

        $this->sitemap->forget();
    }
}
