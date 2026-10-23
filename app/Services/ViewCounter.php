<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Thread view counts, deduplicated per member (or guest session) for
 * config('forum.view_dedupe_minutes'). Cache::add() is atomic, so two
 * simultaneous requests of the same visitor count once.
 */
final class ViewCounter
{
    /**
     * @return bool whether this visit was counted
     */
    public function record(Thread $thread, Request $request): bool
    {
        $key = "forum:thread-views:{$thread->id}:{$this->visitor($request)}";

        if (! Cache::add($key, true, now()->addMinutes((int) config('forum.view_dedupe_minutes')))) {
            return false;
        }

        // A view is not an edit: updated_at must not move.
        Thread::query()->whereKey($thread->id)->toBase()->increment('views_count');
        $thread->forceFill(['views_count' => $thread->views_count + 1])->syncOriginalAttribute('views_count');

        return true;
    }

    private function visitor(Request $request): string
    {
        $user = $request->user();

        return match (true) {
            $user !== null => 'user:'.$user->getAuthIdentifier(),
            $request->hasSession() => 'session:'.$request->session()->getId(),
            default => 'ip:'.hash('sha256', $request->ip().'|'.$request->userAgent()),
        };
    }
}
