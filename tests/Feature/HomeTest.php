<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('renders the home page for guests', function () {
    get(route('home'))->assertOk();
});

it('redirects guests away from the dashboard', function () {
    get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the dashboard to authenticated users', function () {
    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});
