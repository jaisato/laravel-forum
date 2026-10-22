<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Interface languages
    |--------------------------------------------------------------------------
    |
    | The first locale is the forum's default when neither the member, the
    | session nor the browser express a supported preference. Every locale
    | listed here needs a lang/{locale}.json file.
    |
    */

    'locales' => ['es', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'threads' => 20,
        'posts' => 20,
        'search' => 20,
        'notifications' => 20,
        'conversations' => 20,
        'messages' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content limits
    |--------------------------------------------------------------------------
    */

    'threads' => [
        'title_min' => 8,
        'title_max' => 150,
        'body_min' => 10,
        'max_tags' => 5,
    ],

    'posts' => [
        'body_min' => 2,
        'body_max' => 30000,
    ],

    'messages' => [
        'subject_max' => 150,
        'body_max' => 10000,
        'max_recipients' => 10,
    ],

    'profiles' => [
        'bio_max' => 500,
        'location_max' => 80,
        'avatar' => [
            'size' => 256,
            'max_kilobytes' => 2048,
            'disk' => env('FORUM_AVATAR_DISK', 'public'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (attempts per window, per member)
    |--------------------------------------------------------------------------
    |
    | Shared by the web interface and the API through named rate limiters
    | registered in AppServiceProvider.
    |
    */

    'rate_limits' => [
        'threads' => ['attempts' => 3, 'minutes' => 1],
        'replies' => ['attempts' => 6, 'minutes' => 1],
        'reactions' => ['attempts' => 60, 'minutes' => 1],
        'reports' => ['attempts' => 10, 'minutes' => 60],
        'messages' => ['attempts' => 10, 'minutes' => 1],
        'api' => ['authenticated' => 120, 'guest' => 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reputation
    |--------------------------------------------------------------------------
    */

    'reputation' => [
        'reaction' => 1,
        'solution' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence and view counting
    |--------------------------------------------------------------------------
    */

    'online_minutes' => 15,
    'last_seen_throttle_minutes' => 5,
    'view_dedupe_minutes' => 60,
    'stats_cache_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Development seed account
    |--------------------------------------------------------------------------
    |
    | Used by the database seeder only. These are development defaults, not
    | anybody's real credentials; override them from the environment.
    |
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI features (laravel/ai)
    |--------------------------------------------------------------------------
    |
    | Everything is off unless FORUM_AI_ENABLED=true and the matching provider
    | key is configured. Each feature may use its own model; when a feature
    | model is empty the shared text model is used. The Anthropic Messages API
    | rejects sampling parameters, so agents never set a temperature.
    |
    */

    'ai' => [
        'enabled' => (bool) env('FORUM_AI_ENABLED', false),

        'text' => [
            'provider' => env('FORUM_AI_TEXT_PROVIDER', 'anthropic'),
            'model' => env('FORUM_AI_TEXT_MODEL', 'claude-opus-5-5'),
        ],

        'moderation' => [
            'enabled' => (bool) env('FORUM_AI_MODERATION_ENABLED', true),
            'model' => env('FORUM_AI_MODERATION_MODEL'),
            'hide_threshold' => 0.8,
        ],

        'summaries' => [
            'enabled' => (bool) env('FORUM_AI_SUMMARIES_ENABLED', true),
            'model' => env('FORUM_AI_SUMMARY_MODEL'),
            'min_replies' => 5,
            'regenerate_after_replies' => 3,
            'regenerate_after_hours' => 24,
            'max_input_characters' => 60000,
        ],

        'similar' => [
            'enabled' => (bool) env('FORUM_AI_SIMILAR_ENABLED', true),
            'provider' => env('FORUM_AI_EMBEDDINGS_PROVIDER', 'openai'),
            'model' => env('FORUM_AI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('FORUM_AI_EMBEDDINGS_DIMENSIONS', 1536),
            'min_similarity' => 0.75,
            'limit' => 5,
        ],

        'tags' => [
            'enabled' => (bool) env('FORUM_AI_TAGS_ENABLED', true),
            'model' => env('FORUM_AI_TAGS_MODEL'),
            'max' => 3,
        ],
    ],

];
