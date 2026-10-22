<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('seeds a consistent demo forum', function () {
    config(['forum.seed.admin_email' => 'owner@forum.test', 'forum.seed.admin_password' => 'secret-password']);
    Notification::fake();

    $this->seed();

    $admin = User::query()->where('email', 'owner@forum.test')->sole();

    expect($admin->role)->toBe(Role::Admin)
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(User::query()->where('role', Role::Moderator)->exists())->toBeTrue()
        ->and(User::query()->where('role', Role::Member)->count())->toBeGreaterThanOrEqual(10)
        ->and(Category::query()->where('is_locked', true)->exists())->toBeTrue()
        ->and(Thread::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Thread::query()->whereNotNull('solution_post_id')->exists())->toBeTrue()
        ->and(Thread::query()->where('replies_count', '>', 0)->exists())->toBeTrue();

    // Seeding goes through the forum actions, so the counters must already
    // be exactly what forum:recount computes from scratch.
    $this->artisan('forum:recount')
        ->expectsOutputToContain('All counters were already right')
        ->assertSuccessful();

    Notification::assertNothingSent();
});
