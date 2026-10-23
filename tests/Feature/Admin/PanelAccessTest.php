<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('has no login form of its own, so nobody signs in without the second factor', function () {
    // Filament's own form would open a forum session with the password
    // alone, skipping Fortify's two-factor challenge and passkeys.
    expect(Route::has('filament.admin.auth.login'))->toBeFalse();

    $this->get('/admin/login')->assertNotFound();
});

it('sends guests to the forum login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('lets in staff only', function (string $role, int $status) {
    $this->actingAs(forumUser($role))->get('/admin')->assertStatus($status);
})->with([
    'member' => ['member', 403],
    'unverified member' => ['unverified', 403],
    'banned moderator' => ['banned moderator', 403],
    'moderator' => ['moderator', 200],
    'admin' => ['admin', 200],
]);

it('keeps members out in a local environment as well', function () {
    // Filament admits any signed in user in "local" unless the model
    // decides through canAccessPanel().
    config(['app.env' => 'local']);

    $this->actingAs(forumUser('member'))->get('/admin')->assertForbidden();
});
