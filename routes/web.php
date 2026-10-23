<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarkdownPreviewController;
use App\Http\Controllers\Moderation\PostModerationController;
use App\Http\Controllers\Moderation\ThreadModerationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\PostReportController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ThreadSolutionController;
use App\Http\Controllers\ThreadSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('threads', [ThreadController::class, 'index'])->name('threads.index');
// Deleted threads resolve too: staff open them to restore them, the policy
// answers 404 to everyone else.
Route::get('t/{thread:slug}', [ThreadController::class, 'show'])->withTrashed()->name('threads.show');
Route::get('c/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('tags/{tag:slug}', [TagController::class, 'show'])->name('tags.show');

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

// Permissions are checked by the policies (verified and not banned to take
// part), so the routes only ask for a signed in member.
Route::middleware('auth')->group(function () {
    Route::post('markdown/preview', MarkdownPreviewController::class)
        ->middleware('throttle:60,1')
        ->name('markdown.preview');

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
