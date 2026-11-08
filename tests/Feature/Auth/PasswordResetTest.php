<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

it('renders the reset link request screen', function () {
    get(route('password.request'))->assertOk();
});

it('sends a reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset password screen from the emailed token', function () {
    Notification::fake();
    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
        get(route('password.reset', $notification->token))->assertOk();

        return true;
    });
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

it('revokes the API tokens of an account whose password is reset', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->createToken('Un script', ['read']);

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'otra-password',
            'password_confirmation' => 'otra-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    // Whoever took the account and minted a token is out with the password.
    expect($user->tokens()->count())->toBe(0);
});

it('signs out the sessions opened before the password was reset', function () {
    Notification::fake();
    $user = User::factory()->create();
    // What a session opened with the old password keeps of it.
    $opened = Auth::guard('web')->hashPasswordForCookie($user->password);

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'otra-password',
            'password_confirmation' => 'otra-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    // Whoever was signed in with the stolen password is out at their next page.
    actingAs($user->refresh())
        ->withSession(['password_hash_web' => $opened])
        ->get(route('profile.edit'))
        ->assertRedirect(route('login'));

    assertGuest();
});

it('refuses to reset the password with an invalid token', function () {
    $user = User::factory()->create();

    post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});
