<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Filament\Resources\Threads\Pages\ListThreads;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Thread;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(forumUser('moderator'));
});

it('pins, locks and moves a thread', function () {
    $thread = forumThread();
    $category = Category::factory()->create();

    Livewire::test(ListThreads::class)
        ->callAction(TestAction::make('pin')->table($thread))
        ->callAction(TestAction::make('lock')->table($thread))
        ->callAction(TestAction::make('move')->table($thread), data: ['category_id' => $category->id])
        ->assertHasNoFormErrors();

    expect($thread->fresh())
        ->is_pinned->toBeTrue()
        ->is_locked->toBeTrue()
        ->category_id->toBe($category->id)
        ->and(ModerationAction::query()->where('action', ModerationActionType::ThreadMoved)->exists())->toBeTrue();
});

it('unpins and unlocks a thread', function () {
    $thread = Thread::factory()->pinned()->locked()->create();

    Livewire::test(ListThreads::class)
        ->assertActionHidden(TestAction::make('pin')->table($thread))
        ->assertActionHidden(TestAction::make('lock')->table($thread))
        ->callAction(TestAction::make('unpin')->table($thread))
        ->callAction(TestAction::make('unlock')->table($thread));

    expect($thread->fresh())
        ->is_pinned->toBeFalse()
        ->is_locked->toBeFalse();
});

it('moves a thread to another category only', function () {
    $thread = forumThread();

    Livewire::test(ListThreads::class)
        ->callAction(TestAction::make('move')->table($thread), data: ['category_id' => $thread->category_id])
        ->assertHasFormErrors(['category_id']);
});

it('deletes a thread with a reason and restores it', function () {
    $thread = forumThread();

    Livewire::test(ListThreads::class)
        ->callAction(TestAction::make('delete')->table($thread), data: ['reason' => 'Duplicado'])
        ->assertHasNoFormErrors();

    expect(Thread::withTrashed()->find($thread->id)?->trashed())->toBeTrue()
        ->and(ModerationAction::query()->where('action', ModerationActionType::ThreadDeleted)->first()?->reason)
        ->toBe('Duplicado');

    Livewire::test(ListThreads::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$thread])
        ->callAction(TestAction::make('restore')->table($thread));

    expect($thread->fresh()?->trashed())->toBeFalse();
});

it('finds threads by title', function () {
    $wanted = forumThread();
    $wanted->forceFill(['title' => 'Horizon no procesa la cola'])->save();
    $other = forumThread();

    Livewire::test(ListThreads::class)
        ->searchTable('Horizon')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});
