<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackLastSeen
{
    /**
     * Record when a member was last active, at most once every few minutes,
     * for the "who is online" list. The write skips model events and does
     * not touch updated_at: it is presence, not a profile change.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $throttle = (int) config('forum.last_seen_throttle_minutes', 5);

        if ($user !== null && ($user->last_seen_at === null || $user->last_seen_at->lte(now()->subMinutes($throttle)))) {
            $user->timestamps = false;
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
            $user->timestamps = true;
        }

        return $next($request);
    }
}
