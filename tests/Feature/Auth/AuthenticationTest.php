<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the login screen', function () {
    get(route('login'))->assertOk();
});

it('authenticates users with valid credentials', function () {
    $user = User::factory()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));

    assertAuthenticated();
});

it('authenticates users with their username, whatever the casing', function () {
    User::factory()->create(['username' => 'ada_lovelace']);

    post(route('login.store'), [
        'email' => 'Ada_Lovelace',
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));

    assertAuthenticated();
});

it('authenticates users whatever the casing of their email', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    post(route('login.store'), [
        'email' => 'ADA@Example.com',
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));

    assertAuthenticated();
});

it('rejects an unknown username', function () {
    post(route('login.store'), [
        'email' => 'nobody_here',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    assertGuest();
});

it('sends users with two-factor enabled to the challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    assertGuest();
});

it('rejects an invalid password', function () {
    $user = User::factory()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    assertGuest();
});

it('logs users out', function () {
    actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    assertGuest();
});

it('rate limits repeated login attempts', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
