<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * The colour schemes the interface writes to the appearance cookie.
     *
     * @var list<string>
     */
    private const array APPEARANCES = ['light', 'dark', 'system'];

    /**
     * Share the colour scheme the page starts with. The cookie is not
     * encrypted (the browser writes it) and app.blade.php prints it inside
     * an inline script, so only a known value gets there.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, self::APPEARANCES, true) ? $appearance : 'system');

        return $next($request);
    }
}
