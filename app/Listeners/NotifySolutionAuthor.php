<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SolutionMarked;
use App\Models\User;
use App\Notifications\SolutionAccepted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the author of an answer that it solved the thread, unless they
 * marked it themselves.
 */
final class NotifySolutionAuthor implements ShouldQueue
{
    public function handle(SolutionMarked $event): void
    {
        if ((int) $event->post->user_id === $event->actor->id) {
            return;
        }

        User::query()->find($event->post->user_id)?->notify(new SolutionAccepted($event->post, $event->actor));
    }
}
