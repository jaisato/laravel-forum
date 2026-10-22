<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

it('renders the registration screen', function () {
    get(route('register'))->assertOk();
});

it('registers new users', function () {
    post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    assertAuthenticated();
});
