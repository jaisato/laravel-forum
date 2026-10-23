<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's public reads (alias auth.optional): guests read as guests, and
 * a request with a bearer token reads as the token's member, who sees what
 * they see on the web (their reactions and read marks; hidden content, for
 * staff). The token must be valid and carry the read ability: a client that
 * sends one expects to be recognised, so a bad one is an error rather than
 * a guest's visit. As an authentication middleware it runs where
 * auth:sanctum would, before the rate limiter and the route bindings.
 */
final class AuthenticateOptionalToken implements AuthenticatesRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            return $next($request);
        }

        Auth::shouldUse('sanctum');

        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException(guards: ['sanctum']);
        }

        if (! $user->tokenCan('read')) {
            throw new MissingAbilityException(['read']);
        }

        return $next($request);
    }
}
