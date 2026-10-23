<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ConversationMessageController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Moderation\PostModerationController;
use App\Http\Controllers\Api\V1\Moderation\ReportController;
use App\Http\Controllers\Api\V1\Moderation\ThreadModerationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostReactionController;
use App\Http\Controllers\Api\V1\PostReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ThreadController;
use App\Http\Controllers\Api\V1\ThreadPostController;
use App\Http\Controllers\Api\V1\ThreadSolutionController;
use App\Http\Controllers\Api\V1\ThreadSubscriptionController;
use App\Http\Controllers\Api\V1\ThreadSummaryController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (spec §4.7)
|--------------------------------------------------------------------------
|
| Sanctum bearer tokens with abilities (read, write, messages and, for
| staff, moderate). Every route counts against the "api" limiter: 120
| requests a minute per member, 60 per address for guests. Threads and posts
| go by id (threads explicitly: their route key is the slug, for the web);
| categories, tags and members by slug and username.
|
*/

Route::prefix('v1')->name('api.v1.')->where([
    'thread' => '[0-9]+',
    'post' => '[0-9]+',
    'conversation' => '[0-9]+',
    'report' => '[0-9]+',
])->group(function (): void {
    // Signing in: five tries a minute per login and address, besides the
    // general limit.
    Route::post('auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token.store');

    // Reading: guests read as guests, a token reads as its member.
    Route::middleware('auth.optional')->group(function (): void {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

        Route::get('threads', [ThreadController::class, 'index'])->name('threads.index');
        // Deleted threads resolve too: staff read them, the policy answers
        // 404 to everyone else.
        Route::get('threads/{thread:id}', [ThreadController::class, 'show'])->withTrashed()->name('threads.show');
        Route::get('threads/{thread:id}/posts', [ThreadPostController::class, 'index'])->withTrashed()->name('threads.posts.index');
        Route::get('threads/{thread:id}/summary', [ThreadSummaryController::class, 'show'])->name('threads.summary.show');
        Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');

        Route::get('users/{user:username}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user:username}/threads', [UserController::class, 'threads'])->name('users.threads');
        Route::get('users/{user:username}/posts', [UserController::class, 'posts'])->name('users.posts');

        Route::get('search/threads', [SearchController::class, 'threads'])->name('search.threads');
        Route::get('search/posts', [SearchController::class, 'posts'])->name('search.posts');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');

        Route::get('me', [MeController::class, 'show'])->middleware('abilities:read')->name('me.show');
        Route::patch('me', [MeController::class, 'update'])->middleware('abilities:write')->name('me.update');

        // The member's own notifications: reading them, and marking them
        // read, takes the read ability alone.
        Route::middleware('abilities:read')->group(function (): void {
            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::put('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
            // Notification ids are UUIDs: anything else is not found before it
            // reaches a uuid column (PostgreSQL rejects other values).
            Route::put('notifications/{notification}/read', [NotificationController::class, 'read'])
                ->whereUuid('notification')
                ->name('notifications.read');
        });

        // Private messages, reading them included, take their own ability.
        Route::middleware('abilities:messages')->group(function (): void {
            Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
            Route::post('conversations', [ConversationController::class, 'store'])
                ->middleware('throttle:forum-messages')
                ->name('conversations.store');
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
            Route::post('conversations/{conversation}/leave', [ConversationController::class, 'leave'])->name('conversations.leave');
            Route::get('conversations/{conversation}/messages', [ConversationMessageController::class, 'index'])
                ->name('conversations.messages.index');
            Route::post('conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
                ->middleware('throttle:forum-messages')
                ->name('conversations.messages.store');
        });

        // Moderation: staff tokens with the moderate ability (only staff may
        // give it); the policies check the role on every request.
        Route::middleware('abilities:moderate')->prefix('moderation')->name('moderation.')->group(function (): void {
            Route::controller(ThreadModerationController::class)->prefix('threads/{thread:id}')->name('threads.')->group(function (): void {
                Route::put('pin', 'pin')->name('pin');
                Route::delete('pin', 'unpin')->name('unpin');
                Route::put('lock', 'lock')->name('lock');
                Route::delete('lock', 'unlock')->name('unlock');
                Route::post('move', 'move')->name('move');
                Route::post('restore', 'restore')->withTrashed()->name('restore');
            });

            Route::controller(PostModerationController::class)->prefix('posts/{post}')->name('posts.')->group(function (): void {
                Route::post('hide', 'hide')->name('hide');
                Route::post('approve', 'approve')->name('approve');
                Route::post('restore', 'restore')->withTrashed()->name('restore');
            });

            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
            Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');
        });

        // Taking part. Who may (verified, not banned, the thread open…) is
        // the policies' call, as on the web, and so are the forum's limits.
        Route::middleware('abilities:write')->group(function (): void {
            Route::post('threads', [ThreadController::class, 'store'])
                ->middleware('throttle:forum-threads')
                ->name('threads.store');
            Route::patch('threads/{thread:id}', [ThreadController::class, 'update'])->name('threads.update');
            Route::delete('threads/{thread:id}', [ThreadController::class, 'destroy'])->name('threads.destroy');

            Route::post('threads/{thread:id}/posts', [ThreadPostController::class, 'store'])
                ->middleware('throttle:forum-replies')
                ->name('threads.posts.store');
            Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
            Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

            Route::put('posts/{post}/reactions/{type}', [PostReactionController::class, 'update'])
                ->middleware('throttle:forum-reactions')
                ->name('posts.reactions.update');
            Route::delete('posts/{post}/reactions/{type}', [PostReactionController::class, 'destroy'])
                ->middleware('throttle:forum-reactions')
                ->name('posts.reactions.destroy');
            Route::post('posts/{post}/reports', [PostReportController::class, 'store'])
                ->middleware('throttle:forum-reports')
                ->name('posts.reports.store');

            Route::post('threads/{thread:id}/summary', [ThreadSummaryController::class, 'store'])
                ->middleware('throttle:forum-summaries')
                ->name('threads.summary.store');

            Route::put('threads/{thread:id}/solution', [ThreadSolutionController::class, 'update'])->name('threads.solution.update');
            Route::delete('threads/{thread:id}/solution', [ThreadSolutionController::class, 'destroy'])->name('threads.solution.destroy');
            Route::put('threads/{thread:id}/subscription', [ThreadSubscriptionController::class, 'update'])->name('threads.subscription.update');
            Route::delete('threads/{thread:id}/subscription', [ThreadSubscriptionController::class, 'destroy'])->name('threads.subscription.destroy');
        });
    });
});
