<?php

declare(strict_types=1);

use App\Enums\ModerationActionType;
use App\Enums\Role;
use App\Models\ModerationAction;
use App\Models\User;

it('promotes an existing user to administrator', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);

    $this->artisan('forum:make-admin', ['email' => ' ANA@example.com '])
        ->expectsOutputToContain('ana@example.com is now an administrator')
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(Role::Admin)
        ->and(ModerationAction::query()->sole())
        ->action->toBe(ModerationActionType::UserRoleChanged)
        ->moderator_id->toBeNull()
        ->meta->toBe(['from' => 'member', 'to' => 'admin']);
});

it('fails for an unknown email address', function () {
    $this->artisan('forum:make-admin', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user with the email address')
        ->assertFailed();
});

it('does nothing for a user who already is an administrator', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('forum:make-admin', ['email' => $admin->email])->assertSuccessful();

    expect(ModerationAction::query()->count())->toBe(0);
});
