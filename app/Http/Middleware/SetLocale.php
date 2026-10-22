<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * Resolve the interface language: the member's saved preference, then the
     * one chosen in this session, then the browser's, then the forum default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $this->fromBrowser($request),
        ];

        foreach ($candidates as $candidate) {
            if (Locale::isSupported($candidate)) {
                return $candidate;
            }
        }

        return Locale::default();
    }

    /**
     * The first supported language of the Accept-Language header, by quality.
     * Symfony's getPreferredLanguage() would fall back to the first supported
     * locale when nothing matches, hiding the forum's configured default.
     */
    private function fromBrowser(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(strtok($language, '_-') ?: '');

            if (Locale::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
