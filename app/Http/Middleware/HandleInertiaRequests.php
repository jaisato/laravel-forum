<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Ai\ForumAi;
use App\Http\Resources\CurrentUserResource;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly ForumAi $ai) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props every page receives. One-off messages (toasts, a new API token)
     * travel as Inertia flash data instead (Inertia::flash()).
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn (): ?array => $request->user() === null
                    ? null
                    : (new CurrentUserResource($request->user()))->resolve($request),
            ],
            'features' => [
                'ai' => [
                    'summaries' => $this->ai->enabled('summaries'),
                    'similar' => $this->ai->enabled('similar'),
                    'tags' => $this->ai->enabled('tags'),
                ],
            ],
            'locale' => app()->getLocale(),
            'locales' => Locale::supported(),
            'canonical' => fn (): string => $this->canonical($request),
        ];
    }

    /**
     * The address search engines should index the page under (SeoHead):
     * the page itself with its page number, without sorts, filters or
     * anything else in the query.
     */
    private function canonical(Request $request): string
    {
        $page = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);

        return $request->url().($page === false ? '' : '?page='.$page);
    }
}
