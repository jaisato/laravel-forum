<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function storedNotification(User $user, int $ageInDays, bool $read): DatabaseNotification
{
    $createdAt = now()->subDays($ageInDays);

    return DatabaseNotification::query()->forceCreate([
        'id' => Str::uuid()->toString(),
        'type' => 'thread_replied',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => ['title' => 'Test'],
        'read_at' => $read ? $createdAt : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

it('deletes read notifications older than ninety days', function () {
    $user = User::factory()->create();
    $oldRead = storedNotification($user, 91, read: true);
    $oldUnread = storedNotification($user, 200, read: false);
    $recentRead = storedNotification($user, 30, read: true);

    $this->artisan('forum:prune-notifications')->assertSuccessful();

    expect(DatabaseNotification::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$oldUnread->id, $recentRead->id])->sort()->values()->all())
        ->and($oldRead->fresh())->toBeNull();
});

it('takes the age limit as an option', function () {
    $user = User::factory()->create();
    storedNotification($user, 10, read: true);

    $this->artisan('forum:prune-notifications', ['--days' => 7])->assertSuccessful();

    expect(DatabaseNotification::query()->count())->toBe(0);
});
