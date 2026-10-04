<?php

use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

it('renders the welcome page', function () {
    get('/')
        ->assertOk()
        ->assertComponent('Welcome');
});

it('does not disclose the framework or PHP version to visitors', function () {
    get('/')
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->has('canLogin')
            ->has('canRegister')
            ->missing('laravelVersion')
            ->missing('phpVersion'));
});
