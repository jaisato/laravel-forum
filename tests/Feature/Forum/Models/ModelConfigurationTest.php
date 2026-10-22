<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\ThreadStatus;
use App\Models\Category;
use App\Models\ConversationParticipant;
use App\Models\Post;
use App\Models\Report;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\User;
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

it('carries the database defaults on models that were never reloaded', function () {
    // Actions return the model they saved without refreshing it, and the
    // policies read these attributes right away.
    expect(new Thread)
        ->status->toBe(ThreadStatus::Published)
        ->is_pinned->toBeFalse()
        ->is_locked->toBeFalse()
        ->replies_count->toBe(0)
        ->views_count->toBe(0)
        ->and((new Post)->status)->toBe(PostStatus::Published)
        ->and(new User)
        ->role->toBe(Role::Member)
        ->reputation->toBe(0)
        ->threads_count->toBe(0)
        ->posts_count->toBe(0)
        ->and(new Category)
        ->color->toBe('#6366f1')
        ->position->toBe(0)
        ->is_locked->toBeFalse()
        ->threads_count->toBe(0)
        ->posts_count->toBe(0)
        ->and((new Tag)->threads_count)->toBe(0)
        ->and(new Report)
        ->source->toBe(ReportSource::User)
        ->status->toBe(ReportStatus::Open)
        ->and((new ThreadRead)->last_read_post_id)->toBe(0)
        ->and((new ConversationParticipant)->last_read_message_id)->toBe(0);
});
