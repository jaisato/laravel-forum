<?php

declare(strict_types=1);

use App\Enums\ThreadStatus;
use App\Models\Thread;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\File;

it('gives every model a stable polymorphic alias', function () {
    $models = collect(File::files(app_path('Models')))
        ->map(fn (SplFileInfo $file): string => 'App\\Models\\'.$file->getBasename('.php'));

    expect($models)->not->toBeEmpty();

    // The morph map is enforced: a model without an alias would throw the
    // first time it is stored in a polymorphic column (moderation log,
    // notifications, API tokens).
    $models->each(fn (string $model) => expect(Relation::getMorphAlias($model))->not->toBe($model));
});

it('refuses lazy loading outside production to surface N+1 queries', function () {
    Thread::factory()->count(2)->create();

    expect(fn () => Thread::query()->get()->each(fn (Thread $thread) => $thread->category))
        ->toThrow(LazyLoadingViolationException::class);
});

it('refuses to silently discard attributes that are not mass assignable', function () {
    expect(fn () => new Thread(['title' => 'A thread title', 'status' => ThreadStatus::Hidden]))
        ->toThrow(MassAssignmentException::class);
});
