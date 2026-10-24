<?php

use App\Http\Middleware\AuthenticateOptionalToken;
use App\Http\Middleware\EnsureUserCanModerate;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLastSeen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The proxies come from config/trustedproxy.php. Caddy overwrites
        // X-Forwarded-For, -Proto and -Host, but passes a client's
        // X-Forwarded-Port through as it came: the port is never believed
        // (with HTTPS and a host without one, it is 443), nor the forwarded
        // host (Caddy passes the Host header on as it is).
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Every API route counts against the "api" limiter (AppServiceProvider).
        $middleware->throttleApi();

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'auth.optional' => AuthenticateOptionalToken::class,
            'staff' => EnsureUserCanModerate::class,
        ]);

        // A token's abilities, and the web's staff-only routes, are checked
        // before route model binding looks anything up (and after
        // authentication and the limits): whoever may not act gets the same
        // 403 for any id, so they cannot tell which hidden or missing
        // records exist.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: CheckAbilities::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: CheckForAnyAbility::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureUserCanModerate::class);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            TrackLastSeen::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A missing record answers as a hidden one does (the policies'
        // denyAsNotFound()): the 404 names no model and no id.
        $exceptions->map(ModelNotFoundException::class, fn (ModelNotFoundException $exception) => new NotFoundHttpException('Not Found', $exception));

        // Pages answer errors with the forum's own Error page (unless
        // debugging), and an expired form goes back to its page with a
        // warning instead of a dead end.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => __('The page expired. Please try again.')]);

                // 303, not 302: the browser follows it with a GET instead of
                // repeating a PATCH, PUT or DELETE with the expired token.
                return back(303);
            }

            if (config('app.debug') || ! in_array($status, [403, 404, 429, 500, 503], true)) {
                return $response;
            }

            // The page reads the layout's props (the member, their unread
            // notifications…) from the database. When that fails too, as in a
            // database outage, Laravel's plain error page stands in rather
            // than an empty answer; the original error is already reported.
            try {
                // Maintenance mode answers before the web middleware has
                // shared anything: the page still needs the layout's props.
                Inertia::share(app(HandleInertiaRequests::class)->share($request));

                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            } catch (Throwable) {
                return $response;
            }
        });
    })->create();
