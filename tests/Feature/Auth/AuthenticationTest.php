<?php

declare(strict_types=1);

use App\Models\User;
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

it('never tells through the tries left which email belongs to which username', function () {
    User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);
    User::factory()->create(['email' => 'bea@example.com', 'username' => 'bea']);
    $triesLeft = fn (string $login): ?string => post(route('login.store'), ['email' => $login, 'password' => 'wrong'])
        ->headers->get('X-RateLimit-Remaining');

    $triesLeft('ana');

    // Counting per account would leave ana's email one try short.
    expect($triesLeft('ana@example.com'))->toBe($triesLeft('bea@example.com'));
});

it('rate limits repeated login attempts with a name, whatever its casing', function () {
    User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);

    foreach (['ana@example.com', 'Ana@Example.com', ' ana@example.com', 'ANA@EXAMPLE.COM', 'ana@example.com'] as $attempt => $login) {
        post(route('login.store'), ['email' => $login, 'password' => "wrong-{$attempt}"])->assertSessionHasErrors('email');
    }

    post(route('login.store'), ['email' => 'Ana@example.com', 'password' => 'password'])->assertTooManyRequests();
});
