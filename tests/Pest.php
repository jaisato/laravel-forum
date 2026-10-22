<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
|
| Feature tests boot the application against a fresh database for every
| test. Unit tests boot the application too (models and resources reach for
| config and the container) but never touch a connection, so they skip the
| per-test migration cost.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A user playing one of the roles of the permissions matrix (spec §5).
 * "guest" is no user at all.
 */
function forumUser(string $role): ?User
{
    return match ($role) {
        'guest' => null,
        'unverified' => User::factory()->unverified()->create(),
        'banned' => User::factory()->banned()->create(),
        'member' => User::factory()->create(),
        'moderator' => User::factory()->moderator()->create(),
        'banned moderator' => User::factory()->moderator()->banned()->create(),
        'admin' => User::factory()->admin()->create(),
    };
}

/**
 * Whether the gate lets the given user (or a guest) perform the ability.
 */
function allows(?User $user, string $ability, mixed $arguments = []): bool
{
    return Gate::forUser($user)->allows($ability, $arguments);
}
