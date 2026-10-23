<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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
        ->assertRedirect(route('home', absolute: false));

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

it('refuses an email already taken with other casing or spaces', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    post(route('register.store'), registrationData(['email' => '  Alice@Example.com ']))
        ->assertSessionHasErrors('email');

    assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('stores the email trimmed and in lower case', function () {
    post(route('register.store'), registrationData(['email' => ' New.User@Example.com ']));

    expect(User::sole()->email)->toBe('new.user@example.com');
});

it('normalises the email itself for callers other than the registration form', function () {
    // Fortify lowercases the email of the form; other callers (the API) do not.
    User::factory()->create(['email' => 'alice@example.com']);

    expect(fn () => app(CreateNewUser::class)->create(registrationData(['email' => 'Alice@Example.com'])))
        ->toThrow(ValidationException::class);
});
