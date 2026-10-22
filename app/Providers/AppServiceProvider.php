<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ModerationAction;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\ThreadEmbedding;
use App\Models\ThreadRead;
use App\Models\ThreadSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureAuthorization();
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
            'tag' => Tag::class,
            'thread' => Thread::class,
            'thread_embedding' => ThreadEmbedding::class,
            'thread_read' => ThreadRead::class,
            'thread_subscription' => ThreadSubscription::class,
            'user' => User::class,
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
}
