<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('works out whether a member is currently banned', function (array $state, bool $banned) {
    $user = User::factory()->create($state);

    expect($user->isBanned())->toBe($banned);
})->with([
    'never banned' => [[], false],
    'permanent ban' => [['banned_at' => now()->subDay(), 'banned_until' => null], true],
    'temporary ban still running' => [['banned_at' => now()->subDay(), 'banned_until' => now()->addDay()], true],
    'temporary ban expired' => [['banned_at' => now()->subWeek(), 'banned_until' => now()->subMinute()], false],
]);

it('only lets verified, unbanned members participate', function () {
    expect(User::factory()->create()->canParticipate())->toBeTrue()
        ->and(User::factory()->unverified()->create()->canParticipate())->toBeFalse()
        ->and(User::factory()->banned()->create()->canParticipate())->toBeFalse();
});

it('exposes the role through helpers', function () {
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $member = User::factory()->create();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->isStaff())->toBeTrue()
        ->and($moderator->isModerator())->toBeTrue()
        ->and($moderator->isAdmin())->toBeFalse()
        ->and($moderator->isStaff())->toBeTrue()
        ->and($member->role)->toBe(Role::Member)
        ->and($member->isStaff())->toBeFalse();
});

it('wants every email notification until the member opts out', function () {
    $user = User::factory()->create();

    expect($user->wantsEmail('email_replies'))->toBeTrue()
        ->and($user->wantsEmail('email_mentions'))->toBeTrue();

    $user->update(['preferences' => ['email_replies' => false]]);

    expect($user->refresh()->wantsEmail('email_replies'))->toBeFalse()
        ->and($user->wantsEmail('email_mentions'))->toBeTrue()
        ->and($user->wantsEmail('email_unknown'))->toBeFalse();
});

it('merges stored preferences over the defaults', function () {
    $user = User::factory()->create(['preferences' => ['allow_private_messages' => false]]);

    expect($user->preferences)->toBe([
        ...User::DEFAULT_PREFERENCES,
        'allow_private_messages' => false,
    ]);
});

it('builds the avatar url from the public disk', function () {
    Storage::fake('public');

    expect(User::factory()->create()->avatar_url)->toBeNull();

    $user = User::factory()->create(['avatar_path' => 'avatars/ada.webp']);

    expect($user->avatar_url)->toBe(Storage::disk('public')->url('avatars/ada.webp'));
});

it('stores usernames in lower case', function () {
    $user = User::factory()->create(['username' => 'Ada_Lovelace']);

    expect($user->username)->toBe('ada_lovelace');
});

it('finds members seen in the last minutes', function () {
    $recent = User::factory()->create(['last_seen_at' => now()->subMinutes(3)]);
    User::factory()->create(['last_seen_at' => now()->subMinutes(30)]);
    User::factory()->create(['last_seen_at' => null]);

    expect(User::online(15)->pluck('id')->all())->toBe([$recent->id]);
});

it('stores email addresses in lower case so lookups work on PostgreSQL', function () {
    $user = User::factory()->create(['email' => '  Ada@Example.COM ']);

    expect($user->email)->toBe('ada@example.com');
});
