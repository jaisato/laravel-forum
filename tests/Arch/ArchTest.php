<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Moderation\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\Moderation\PostModerationController;
use App\Http\Controllers\Moderation\ThreadModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\MessageBodyRules;
use App\Http\Requests\PostBodyRules;
use App\Http\Requests\ThreadRules;
use App\Listeners\NotifyMentionedUsers;
use App\Listeners\QueueAiModeration;
use App\Listeners\QueueThreadEmbedding;
use App\Listeners\SyncThreadSearchIndex;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\ForumStats;
use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

// laravel/pint autoloads its own sources under App\ (vendor/laravel/pint/app),
// and Pest maps namespaces to folders from Composer's PSR-4 prefixes: without
// this, the rules would judge Pint's classes as if they were the forum's.
foreach (ClassLoader::getRegisteredLoaders() as $loader) {
    $loader->setPsr4('App\\', [dirname(__DIR__, 2).'/app']);
}

arch('debugging helpers are never committed')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

// pest-plugin-arch fails on a namespace that only holds sub-namespaces,
// so the rule lists them from the folders (new ones are picked up).
arch('actions are final classes')
    ->expect(array_map(
        fn (string $directory): string => 'App\\Actions\\'.basename($directory),
        glob(dirname(__DIR__, 2).'/app/Actions/*', GLOB_ONLYDIR) ?: [],
    ))
    ->toBeFinal();

arch('domain events are dispatched only once the transaction commits')
    ->expect('App\Events')
    ->classes()
    ->toImplement(ShouldDispatchAfterCommit::class);

// Laravel's conventions, but for the exceptions the forum chose on purpose:
// - controllers whose verbs are moderation or inbox commands (pin, lock,
//   hide, resolve, read, leave…), each one domain action, rather than one
//   controller per verb;
// - listeners that follow several events through event discovery's
//   handle{Event}() methods;
// - the validation rules the web and API form requests share, and the
//   requests' concerns;
// - the home page's cached figures, built with the UserResource the pages
//   use (the cache holds arrays, not models).
arch()->preset()->laravel()->ignoring([
    ConversationController::class,
    NotificationController::class,
    PostModerationController::class,
    ThreadModerationController::class,
    App\Http\Controllers\Api\V1\ConversationController::class,
    App\Http\Controllers\Api\V1\NotificationController::class,
    SearchController::class,
    UserController::class,
    App\Http\Controllers\Api\V1\Moderation\PostModerationController::class,
    ReportController::class,
    App\Http\Controllers\Api\V1\Moderation\ThreadModerationController::class,
    NotifyMentionedUsers::class,
    QueueAiModeration::class,
    QueueThreadEmbedding::class,
    SyncThreadSearchIndex::class,
    MessageBodyRules::class,
    PostBodyRules::class,
    ThreadRules::class,
    'App\Http\Requests\Concerns',
    ForumStats::class,
    // Notification channels deliver notifications; they are not ones.
    'App\Notifications\Channels',
    // Filament's own naming for its panels.
    AdminPanelProvider::class,
]);

arch()->preset()->security();

arch('the application declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

// Fortify's actions answer its contracts (create, reset, update); the
// forum's own do one thing each, through handle().
arch('domain actions do their work in handle()')
    ->expect(array_values(array_filter(
        array_map(
            fn (string $directory): string => 'App\\Actions\\'.basename($directory),
            glob(dirname(__DIR__, 2).'/app/Actions/*', GLOB_ONLYDIR) ?: [],
        ),
        fn (string $namespace): bool => $namespace !== 'App\\Actions\\Fortify',
    )))
    ->toHaveMethod('handle');
