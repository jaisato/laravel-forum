<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\ModerationAction;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lets moderators ban a member, with a reason and an optional end', function () {
    $moderator = forumUser('moderator');
    $member = User::factory()->create();
    $member->createToken('app');
    $until = now()->addWeek()->startOfMinute();

    $this->actingAs($moderator);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('ban')->table($member), data: [
            'reason' => 'Spam repetido',
            'until' => $until->format('Y-m-d H:i:s'),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $member->refresh();

    expect($member->isBanned())->toBeTrue()
        ->and($member->ban_reason)->toBe('Spam repetido')
        ->and($member->banned_until?->equalTo($until))->toBeTrue()
        ->and($member->tokens()->count())->toBe(0)
        ->and(ModerationAction::query()
            ->where('action', ModerationActionType::UserBanned)
            ->where('moderator_id', $moderator->id)
            ->where('subject_id', $member->id)
            ->exists())->toBeTrue();
});

it('bans for good when no end is given, and asks for a reason', function () {
    $member = User::factory()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('ban')->table($member), data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($member->fresh()->isBanned())->toBeFalse();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('ban')->table($member), data: ['reason' => 'Acoso'])
        ->assertHasNoFormErrors();

    expect($member->fresh())
        ->banned_until->toBeNull()
        ->isBanned()->toBeTrue();
});

it('lets moderators ban members only, and nobody themselves', function () {
    $moderator = forumUser('moderator');
    $this->actingAs($moderator);

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('ban')->table(User::factory()->create()))
        ->assertActionHidden(TestAction::make('ban')->table(User::factory()->moderator()->create()))
        ->assertActionHidden(TestAction::make('ban')->table(User::factory()->admin()->create()))
        ->assertActionHidden(TestAction::make('ban')->table($moderator));
});

it('lets administrators ban moderators, never other administrators', function () {
    $this->actingAs(forumUser('admin'));

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('ban')->table(User::factory()->moderator()->create()))
        ->assertActionHidden(TestAction::make('ban')->table(User::factory()->admin()->create()));
});

it('lifts a ban', function () {
    $banned = User::factory()->banned()->create();
    $member = User::factory()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('unban')->table($member))
        ->callAction(TestAction::make('unban')->table($banned));

    expect($banned->fresh()->isBanned())->toBeFalse()
        ->and(ModerationAction::query()->where('action', ModerationActionType::UserUnbanned)->exists())->toBeTrue();
});

it('lets administrators change roles, never their own', function () {
    $admin = forumUser('admin');
    $member = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('changeRole')->table($admin))
        ->callAction(TestAction::make('changeRole')->table($member), data: ['role' => Role::Moderator->value])
        ->assertHasNoFormErrors();

    expect($member->fresh()->role)->toBe(Role::Moderator)
        ->and(ModerationAction::query()->where('action', ModerationActionType::UserRoleChanged)->first()?->meta)
        ->toBe(['from' => 'member', 'to' => 'moderator']);
});

it('does not let moderators change roles', function () {
    $member = User::factory()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('changeRole')->table($member));

    Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])
        ->assertActionHidden('changeRole')
        ->assertActionVisible('ban');
});

it('shows emails to administrators only', function () {
    User::factory()->create();

    $this->actingAs(forumUser('moderator'));
    Livewire::test(ListUsers::class)->assertCanNotRenderTableColumn('email');

    $this->actingAs(forumUser('admin'));
    Livewire::test(ListUsers::class)->assertCanRenderTableColumn('email');
});

it('finds members by username', function () {
    // Names and emails fixed too: they are searchable, and a random one may hold "ana".
    $ana = User::factory()->create(['username' => 'ana', 'name' => 'Ana Torres', 'email' => 'ana@example.com']);
    $luis = User::factory()->create(['username' => 'luis', 'name' => 'Luis Pérez', 'email' => 'luis@example.com']);
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListUsers::class)
        ->searchTable('ana')
        ->assertCanSeeTableRecords([$ana])
        ->assertCanNotSeeTableRecords([$luis]);
});

it('lists apart the members banned right now', function () {
    $banned = User::factory()->banned()->create();
    $lapsed = User::factory()->banned(now()->subDay())->create();
    $member = User::factory()->create();
    $this->actingAs(forumUser('moderator'));

    Livewire::test(ListUsers::class)
        ->filterTable('banned')
        ->assertCanSeeTableRecords([$banned])
        ->assertCanNotSeeTableRecords([$lapsed, $member]);
});
