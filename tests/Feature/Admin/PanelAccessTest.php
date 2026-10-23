<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
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

it('shows each role its own part of the panel (spec §4.8)', function (string $role, array $hidden) {
    // One role per test: the panel builds its navigation once per application.
    $all = ['/admin/reports', '/admin/posts', '/admin/threads', '/admin/users', '/admin/moderation-actions', '/admin/categories', '/admin/tags'];

    $this->actingAs(forumUser($role))->get('/admin')
        ->assertOk()
        ->assertSee(array_map(fn (string $path): string => url($path), array_diff($all, $hidden)), escape: false)
        ->assertDontSee(array_map(fn (string $path): string => url($path), $hidden), escape: false);
})->with([
    'moderator' => ['moderator', ['/admin/categories', '/admin/tags']],
    'admin' => ['admin', []],
]);

it('keeps moderators out of categories and tags', function (string $path) {
    $this->actingAs(forumUser('moderator'))->get($path)->assertForbidden();
    $this->actingAs(forumUser('admin'))->get($path)->assertOk();
})->with(['/admin/categories', '/admin/tags']);

it('opens every moderation page to moderators', function (string $path) {
    $this->actingAs(forumUser('moderator'))->get($path)->assertOk();
})->with(['/admin/reports', '/admin/posts', '/admin/threads', '/admin/users', '/admin/moderation-actions']);

it('speaks the language of each member of the staff', function () {
    $this->actingAs(User::factory()->admin()->create(['locale' => 'es']))->get('/admin')
        ->assertOk()
        ->assertSee('Categorías')
        ->assertSee('Registro de moderación');
});

it('opens Horizon to administrators only', function (string $role, int $status) {
    $user = forumUser($role);

    if ($user !== null) {
        $this->actingAs($user);
    }

    $this->get('/horizon')->assertStatus($status);
})->with([
    'guest' => ['guest', 403],
    'member' => ['member', 403],
    'moderator' => ['moderator', 403],
    'banned admin' => ['banned admin', 403],
    'admin' => ['admin', 200],
]);

it('fails closed: a check without its policy method refuses instead of allowing', function () {
    // Filament otherwise treats a missing policy method as permission.
    expect(Filament::getPanel('admin')->isAuthorizationStrict())->toBeTrue();
});
