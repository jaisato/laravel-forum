<?php

declare(strict_types=1);

use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\ChangeUserRole;
use App\Actions\Moderation\UnbanUser;
use App\Enums\ModerationActionType;
use App\Enums\Role;
use App\Models\ModerationAction;
use App\Models\User;

it('bans a member, revoking their API tokens', function () {
    $member = User::factory()->create();
    $member->createToken('cli', ['read', 'write']);
    $moderator = User::factory()->moderator()->create();
    $this->freezeSecond();

    app(BanUser::class)->handle($member, $moderator, 'Repeated spam');

    expect($member->fresh())
        ->isBanned()->toBeTrue()
        ->ban_reason->toBe('Repeated spam')
        ->banned_until->toBeNull()
        ->and($member->tokens()->count())->toBe(0)
        ->and(ModerationAction::query()->sole())
        ->action->toBe(ModerationActionType::UserBanned)
        ->reason->toBe('Repeated spam')
        ->meta->toBe(['until' => null]);
});

it('lets a temporary ban lapse on its own', function () {
    $member = User::factory()->create();
    $until = now()->addDays(3);

    app(BanUser::class)->handle($member, User::factory()->moderator()->create(), 'Cool down', $until);

    expect($member->fresh()->isBanned())->toBeTrue()
        ->and(ModerationAction::query()->sole()->meta)->toBe(['until' => $until->toIso8601String()]);

    $this->travel(4)->days();

    expect($member->fresh()->isBanned())->toBeFalse();
});

it('lifts a ban, once', function () {
    $member = User::factory()->banned()->create();
    $moderator = User::factory()->moderator()->create();

    app(UnbanUser::class)->handle($member, $moderator);
    app(UnbanUser::class)->handle($member, $moderator);

    expect($member->fresh())
        ->isBanned()->toBeFalse()
        ->banned_at->toBeNull()
        ->ban_reason->toBeNull()
        ->and(ModerationAction::query()->sole()->action)->toBe(ModerationActionType::UserUnbanned);
});

it('changes the role of a user, logging where it came from', function () {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    app(ChangeUserRole::class)->handle($member, $admin, Role::Moderator);
    app(ChangeUserRole::class)->handle($member, $admin, Role::Moderator);

    expect($member->fresh()->role)->toBe(Role::Moderator)
        ->and(ModerationAction::query()->sole())
        ->action->toBe(ModerationActionType::UserRoleChanged)
        ->meta->toBe(['from' => 'member', 'to' => 'moderator']);
});
