<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'not-banned'])
        ->post('/_test/participate', fn () => response('ok'));
});

it('lets members who are not banned through', function () {
    actingAs(User::factory()->create())
        ->post('/_test/participate')
        ->assertOk();
});

it('sends banned members back with an explanation', function () {
    actingAs(User::factory()->banned()->create())
        ->from('/somewhere')
        ->post('/_test/participate')
        ->assertRedirect('/somewhere')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Your account is suspended, so you cannot post right now.',
        ]);
});

it('answers API clients with a 403', function () {
    actingAs(User::factory()->banned()->create())
        ->postJson('/_test/participate')
        ->assertForbidden()
        ->assertJson(['message' => 'Your account is suspended, so you cannot post right now.']);
});

it('lets members back in once a temporary ban expires', function () {
    actingAs(User::factory()->banned(now()->subMinute())->create())
        ->post('/_test/participate')
        ->assertOk();
});
