<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PostStatus;
use App\Events\PostDeleted;
use App\Events\PostModerated;
use App\Events\PostUpdated;
use App\Models\Thread;

/**
 * A thread's AI summary restates its posts, so it goes when one of them
 * changes or leaves public view: edited, hidden, held for review or
 * deleted. Hiding the post is therefore also how the staff take down a
 * summary that repeats it. Approving a post changes nothing: the summary
 * never quoted it, and the next one will.
 */
final class ForgetThreadSummary
{
    public function handle(PostUpdated|PostDeleted|PostModerated $event): void
    {
        if ($event instanceof PostModerated && $event->status === PostStatus::Published) {
            return;
        }

        Thread::forgetSummaries(Thread::query()->whereKey($event->post->thread_id));
    }
}
