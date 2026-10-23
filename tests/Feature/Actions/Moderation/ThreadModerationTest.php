<?php

declare(strict_types=1);

use App\Actions\Moderation\LockThread;
use App\Actions\Moderation\MoveThread;
use App\Actions\Moderation\PinThread;
use App\Enums\ModerationActionType;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Thread;
use App\Models\User;

it('pins and unpins a thread, logging each change once', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    app(PinThread::class)->handle($thread, $moderator);
    app(PinThread::class)->handle($thread, $moderator);

    expect($thread->fresh()->is_pinned)->toBeTrue();

    app(PinThread::class)->handle($thread, $moderator, pinned: false);

    expect($thread->fresh()->is_pinned)->toBeFalse()
        ->and(ModerationAction::query()->oldest('id')->pluck('action')->all())
        ->toBe([ModerationActionType::ThreadPinned, ModerationActionType::ThreadUnpinned]);
});

it('locks and unlocks a thread, logging each change once', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    app(LockThread::class)->handle($thread, $moderator);
    app(LockThread::class)->handle($thread, $moderator);

    expect($thread->fresh()->is_locked)->toBeTrue();

    app(LockThread::class)->handle($thread, $moderator, locked: false);

    expect($thread->fresh()->is_locked)->toBeFalse()
        ->and(ModerationAction::query()->oldest('id')->pluck('action')->all())
        ->toBe([ModerationActionType::ThreadLocked, ModerationActionType::ThreadUnlocked]);
});

it('logs the change once when two requests race to make it', function (string $action, string $flag, ModerationActionType $logged) {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();
    // A double click: both requests load the thread before either saves it.
    $first = Thread::query()->findOrFail($thread->id);
    $second = Thread::query()->findOrFail($thread->id);

    app($action)->handle($first, $moderator);
    app($action)->handle($second, $moderator);

    expect($thread->fresh()->{$flag})->toBeTrue()
        ->and(ModerationAction::query()->where('action', $logged)->count())->toBe(1);
})->with([
    'pin' => [PinThread::class, 'is_pinned', ModerationActionType::ThreadPinned],
    'lock' => [LockThread::class, 'is_locked', ModerationActionType::ThreadLocked],
]);

it('undoes the change asked for even from a copy loaded before it was made', function (string $action, string $flag, string $argument) {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();
    $stale = Thread::query()->findOrFail($thread->id);

    app($action)->handle($thread, $moderator);
    app($action)->handle($stale, $moderator, ...[$argument => false]);

    expect($thread->fresh()->{$flag})->toBeFalse()
        ->and($stale->{$flag})->toBeFalse()
        ->and(ModerationAction::query()->count())->toBe(2);
})->with([
    'unpin' => [PinThread::class, 'is_pinned', 'pinned'],
    'unlock' => [LockThread::class, 'is_locked', 'locked'],
]);

it('moves a thread with its counters to another category', function () {
    $from = Category::factory()->create();
    $to = Category::factory()->create();
    $thread = forumThread(category: $from);
    forumReply($thread);
    forumReply(forumThread(category: $from));
    $moderator = User::factory()->moderator()->create();

    app(MoveThread::class)->handle($thread, $to, $moderator);

    expect($thread->fresh()->category_id)->toBe($to->id)
        ->and($from->fresh())->threads_count->toBe(1)->posts_count->toBe(2)
        ->and($to->fresh())->threads_count->toBe(1)->posts_count->toBe(2)
        ->and(ModerationAction::query()->sole())
        ->action->toBe(ModerationActionType::ThreadMoved)
        ->meta->toBe(['from_category_id' => $from->id, 'to_category_id' => $to->id]);
});

it('does nothing when a thread is moved to its own category', function () {
    $thread = forumThread();

    app(MoveThread::class)->handle($thread, $thread->category, User::factory()->moderator()->create());

    expect($thread->category->fresh()->threads_count)->toBe(1)
        ->and(ModerationAction::query()->count())->toBe(0);
});
