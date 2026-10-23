<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

it('displays the profile page', function () {
    actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

it('updates the profile information', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

it('sends a verification link to a new email address', function () {
    Notification::fake();
    $user = User::factory()->create();

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'nuevo@example.com',
    ]);

    // Unverified members cannot post, so they need the link right away.
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('normalises the email before checking it is free', function () {
    User::factory()->create(['email' => 'alice@example.com']);
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => ' Alice@Example.COM ',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => ' Bob.New@Example.COM ',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('bob.new@example.com');
});

it('keeps the verification status when the email is unchanged', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('keeps the verification status when only the case of the email changes', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@example.com']);

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'Ana@Example.com',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('lets users delete their account', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    assertGuest();
    // Accounts are soft deleted so the member's posts keep their author row.
    expect($user->fresh()->trashed())->toBeTrue();
});

it('requires the correct password to delete the account', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
