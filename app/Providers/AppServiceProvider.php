<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\ForumAi;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\RetiredUsername;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\ThreadEmbedding;
use App\Models\ThreadRead;
use App\Models\ThreadSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\Channels\ForumDatabaseChannel;
use App\Services\Ai\KeywordSimilarThreadFinder;
use App\Services\Ai\SimilarThreadFinder;
use App\Services\Ai\VectorSimilarThreadFinder;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseChannel::class, ForumDatabaseChannel::class);
        // Decided at each resolution: the feature and the database may change.
        $this->app->bind(SimilarThreadFinder::class, fn (Application $app): SimilarThreadFinder => $app->make(ForumAi::class)->usesVectorSearch()
            ? $app->make(VectorSimilarThreadFinder::class)
            : $app->make(KeywordSimilarThreadFinder::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureApiDocs();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure Eloquent: stable polymorphic aliases everywhere, and strict
     * mode outside production so N+1 queries and dropped attributes fail
     * loudly in development and tests.
     */
    protected function configureModels(): void
    {
        // Polymorphic columns (moderation log, notifications, API tokens)
        // store these aliases instead of class names, so renaming or moving
        // a model never orphans existing rows.
        Relation::enforceMorphMap([
            'category' => Category::class,
            'conversation' => Conversation::class,
            'conversation_participant' => ConversationParticipant::class,
            'message' => Message::class,
            'moderation_action' => ModerationAction::class,
            'post' => Post::class,
            'reaction' => Reaction::class,
            'report' => Report::class,
            'retired_username' => RetiredUsername::class,
            'tag' => Tag::class,
            'thread' => Thread::class,
            'thread_embedding' => ThreadEmbedding::class,
            'thread_read' => ThreadRead::class,
            'thread_subscription' => ThreadSubscription::class,
            'user' => User::class,
            'notification' => UserNotification::class,
        ]);

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
    }

    /**
     * Gates that are not about a model. Model abilities live in app/Policies
     * and viewHorizon in HorizonServiceProvider.
     */
    protected function configureAuthorization(): void
    {
        // Moderators get the moderation resources, administrators all of it.
        Gate::define('access-admin', fn (User $user): bool => $user->canModerate());

        // The API is public, so is its documentation (Scramble only opens it
        // in the local environment by default).
        Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
    }

    /**
     * The OpenAPI document (Scramble, /docs/api): every operation takes a
     * Sanctum bearer token unless its method says @unauthenticated (signing
     * in and the public reads, where a token stays optional).
     */
    protected function configureApiDocs(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer')->as('bearerAuth'));

            // @unauthenticated reads still take a token, which makes the
            // member's view (staff see hidden content): an optional bearer.
            // Asking for a token takes none.
            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    if ($operation->security === [] && ltrim($path->path, '/') !== 'auth/token') {
                        $operation->security = [new SecurityRequirement([]), new SecurityRequirement(['bearerAuth' => []])];
                    }
                }
            }
        });
    }

    /**
     * The forum's named rate limiters (forum-threads, forum-replies,
     * forum-reactions, forum-reports, forum-messages), shared by the web and
     * the API and counted per member. A web form that hits one goes back with
     * a toast; an API client gets the usual 429. Then the API's own: "api"
     * for every route and "api-token" for signing in.
     */
    protected function configureRateLimiting(): void
    {
        // The token is read here, before any route asks for it, so members
        // count as members on the public routes too.
        RateLimiter::for('api', function (Request $request): Limit {
            $member = $request->user('sanctum');

            return $member === null
                ? Limit::perMinute((int) config('forum.rate_limits.api.guest'))->by('address:'.$request->ip())
                : Limit::perMinute((int) config('forum.rate_limits.api.authenticated'))->by('member:'.$member->getAuthIdentifier());
        });

        // Asking for a token is signing in: five tries a minute per login and
        // address, as on the login form (FortifyServiceProvider).
        RateLimiter::for('api-token', function (Request $request): Limit {
            $login = $request->input('email') ?: $request->input('username');

            return Limit::perMinute(5)->by(
                (is_string($login) ? Str::transliterate(Str::lower(trim($login))) : '').'|'.$request->ip(),
            );
        });

        foreach (config('forum.rate_limits') as $name => $limit) {
            if (! isset($limit['attempts'], $limit['minutes'])) {
                continue;
            }

            RateLimiter::for("forum-{$name}", fn (Request $request): Limit => Limit::perMinutes((int) $limit['minutes'], (int) $limit['attempts'])
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
                ->response(function (Request $request, array $headers): Response {
                    // API clients get JSON whatever they say they accept.
                    if ($request->is('api/*') || $request->expectsJson()) {
                        return response()->json(['message' => __('Too many attempts. Please wait a moment.')], 429, $headers);
                    }

                    Inertia::flash('toast', ['type' => 'error', 'message' => __('Too many attempts. Please wait a moment.')]);

                    return back()->withHeaders($headers);
                }));
        }
    }
}
