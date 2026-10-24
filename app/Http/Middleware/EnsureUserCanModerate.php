<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web's moderation routes are for staff only. Anybody else is turned
 * away here, before route model binding looks their thread or post up, with
 * the same 403 whether it is published, hidden, deleted or was never there:
 * the answer tells them nothing (the API's abilities run as early). The
 * policies still decide each action for the staff.
 */
final class EnsureUserCanModerate
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canModerate() === true, 403);

        return $next($request);
    }
}
