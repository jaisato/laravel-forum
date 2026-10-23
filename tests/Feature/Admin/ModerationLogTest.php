<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Filament\Resources\ModerationActions\Pages\ListModerationActions;
use App\Models\ModerationAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(forumUser('moderator'));
});

it('lists the moderation log, newest first, without a way to change it', function () {
    $older = ModerationAction::factory()->create(['created_at' => now()->subDay(), 'reason' => 'Fuera de tono']);
    $newer = ModerationAction::factory()->system()->create(['action' => ModerationActionType::UserRoleChanged]);

    Livewire::test(ListModerationActions::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertSee('Fuera de tono')
        // Entries without a moderator come from the system: the AI, the console.
        ->assertSee('System')
        ->assertActionDoesNotExist('create');
});

it('filters the log by action', function () {
    $hidden = ModerationAction::factory()->create(['action' => ModerationActionType::PostHidden]);
    $banned = ModerationAction::factory()->create(['action' => ModerationActionType::UserBanned]);

    Livewire::test(ListModerationActions::class)
        ->filterTable('action', ModerationActionType::UserBanned->value)
        ->assertCanSeeTableRecords([$banned])
        ->assertCanNotSeeTableRecords([$hidden]);
});
