<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkdownPreviewController;
use App\Http\Controllers\Moderation\PostModerationController;
use App\Http\Controllers\Moderation\ThreadModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ThreadSolutionController;
use App\Http\Controllers\ThreadSubscriptionController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('threads', [ThreadController::class, 'index'])->name('threads.index');
// Deleted threads resolve too: staff open them to restore them, the policy
// answers 404 to everyone else.
Route::get('t/{thread:slug}', [ThreadController::class, 'show'])->withTrashed()->name('threads.show');
Route::get('c/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');
Route::get('u/{user:username}', [UserProfileController::class, 'show'])->name('users.show');
// Every search reaches the search engine.
Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');

// Permissions are checked by the policies (verified and not banned to take
// part), so the routes only ask for a signed in member.
Route::middleware('auth')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    // Notification ids are UUIDs: anything else is not found before it
    // reaches a uuid column (PostgreSQL rejects other values).
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereUuid('notification')
        ->name('notifications.read');

    Route::get('messages', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('messages/create', [ConversationController::class, 'create'])->name('conversations.create');
    Route::post('messages', [ConversationController::class, 'store'])
        ->middleware('throttle:forum-messages')
        ->name('conversations.store');
    Route::get('messages/{conversation}', [ConversationController::class, 'show'])
        ->whereNumber('conversation')
        ->name('conversations.show');
    Route::post('messages/{conversation}', [ConversationMessageController::class, 'store'])
        ->whereNumber('conversation')
        ->middleware('throttle:forum-messages')
        ->name('conversations.messages.store');
    Route::post('messages/{conversation}/leave', [ConversationController::class, 'leave'])
        ->whereNumber('conversation')
        ->name('conversations.leave');

    Route::post('markdown/preview', MarkdownPreviewController::class)
        ->middleware('throttle:60,1')
        ->name('markdown.preview');

    Route::get('threads/create', [ThreadController::class, 'create'])->name('threads.create');
    Route::post('threads', [ThreadController::class, 'store'])
        ->middleware('throttle:forum-threads')
        ->name('threads.store');
    Route::patch('t/{thread:slug}', [ThreadController::class, 'update'])->name('threads.update');
    Route::delete('t/{thread:slug}', [ThreadController::class, 'destroy'])->name('threads.destroy');

    Route::post('t/{thread:slug}/posts', [PostController::class, 'store'])
        ->middleware('throttle:forum-replies')
        ->name('posts.store');
    Route::patch('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    Route::post('posts/{post}/reactions', PostReactionController::class)
        ->middleware('throttle:forum-reactions')
        ->name('posts.reactions.toggle');
    Route::post('posts/{post}/reports', [PostReportController::class, 'store'])
        ->middleware('throttle:forum-reports')
        ->name('posts.reports.store');

    Route::post('t/{thread:slug}/subscription', [ThreadSubscriptionController::class, 'store'])->name('threads.subscription.store');
    Route::delete('t/{thread:slug}/subscription', [ThreadSubscriptionController::class, 'destroy'])->name('threads.subscription.destroy');

    Route::post('t/{thread:slug}/solution', [ThreadSolutionController::class, 'store'])->name('threads.solution.store');
    Route::delete('t/{thread:slug}/solution', [ThreadSolutionController::class, 'destroy'])->name('threads.solution.destroy');

    Route::prefix('moderation')->name('moderation.')->group(function () {
        Route::controller(ThreadModerationController::class)->prefix('threads/{thread:slug}')->name('threads.')->group(function () {
            Route::post('pin', 'pin')->name('pin');
            Route::post('unpin', 'unpin')->name('unpin');
            Route::post('lock', 'lock')->name('lock');
            Route::post('unlock', 'unlock')->name('unlock');
            Route::post('move', 'move')->name('move');
            Route::post('restore', 'restore')->withTrashed()->name('restore');
        });

        Route::controller(PostModerationController::class)->prefix('posts/{post}')->name('posts.')->group(function () {
            Route::post('hide', 'hide')->name('hide');
            Route::post('approve', 'approve')->name('approve');
            Route::post('restore', 'restore')->withTrashed()->name('restore');
        });
    });
});

require __DIR__.'/settings.php';

// Unknown addresses go through the web middleware too, so their 404 page
// knows the member, the language and the rest of the shared props. Every
// method, as Route::fallback() alone would answer 405 to anything but GET.
Route::any('{fallbackPlaceholder}', fn () => abort(404))
    ->where('fallbackPlaceholder', '.*')
    ->fallback();
