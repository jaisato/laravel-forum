<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsNotBanned
{
    /**
     * Banned members keep read access but every write route is guarded by
     * this middleware (alias `not-banned`). Browsers go back to the page they
     * came from with an explanation; API clients receive a 403.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBanned() !== true) {
            return $next($request);
        }

        $message = __('Your account is suspended, so you cannot post right now.');

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
