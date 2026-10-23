<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;
use App\Services\ThreadSubscriptions;

it('subscribes a member once however often they ask', function () {
    $thread = Thread::factory()->create();
    $member = User::factory()->create();
    $subscriptions = app(ThreadSubscriptions::class);

    $subscriptions->subscribe($thread, $member);
    $subscriptions->subscribe($thread, $member);

    expect($subscriptions->isSubscribed($thread, $member))->toBeTrue()
        ->and($thread->subscriptions()->count())->toBe(1);
});

it('unsubscribes a member, even twice', function () {
    $thread = Thread::factory()->create();
    $member = User::factory()->create();
    $subscriptions = app(ThreadSubscriptions::class);
    $subscriptions->subscribe($thread, $member);

    $subscriptions->unsubscribe($thread, $member);
    $subscriptions->unsubscribe($thread, $member);

    expect($subscriptions->isSubscribed($thread, $member))->toBeFalse();
});
