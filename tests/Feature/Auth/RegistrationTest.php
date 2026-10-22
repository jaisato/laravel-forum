<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Features;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

it('renders the registration screen', function () {
    get(route('register'))->assertOk();
});

function registrationData(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'username' => 'test_user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

it('registers new users', function () {
    post(route('register.store'), registrationData())
        ->assertRedirect(route('dashboard', absolute: false));

    assertAuthenticated();
});

it('stores the username in lower case', function () {
    post(route('register.store'), registrationData(['username' => 'Test_User']));

    expect(User::sole()->username)->toBe('test_user');
});

it('requires a valid, available username', function (mixed $username) {
    User::factory()->create(['username' => 'taken_name']);

    post(route('register.store'), registrationData(['username' => $username]))
        ->assertSessionHasErrors('username');

    assertGuest();
})->with([
    'missing' => [''],
    'too short' => ['ab'],
    'too long' => [str_repeat('a', 31)],
    'spaces' => ['two words'],
    'symbols' => ['hello!'],
    'taken' => ['taken_name'],
    'taken with other casing' => ['Taken_Name'],
    'reserved' => ['admin'],
    'reserved prefix of erased accounts' => ['deleted-42'],
]);
