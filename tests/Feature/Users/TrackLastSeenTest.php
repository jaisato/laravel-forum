<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('records when a member was last seen', function () {
    $user = User::factory()->create(['last_seen_at' => null]);

    actingAs($user)->get(route('home'))->assertOk();

    expect($user->refresh()->last_seen_at)->not->toBeNull();
});

it('writes the timestamp at most every five minutes', function () {
    $this->freezeSecond();
    $seen = now()->subMinutes(2);
    $user = User::factory()->create(['last_seen_at' => $seen]);

    actingAs($user)->get(route('home'));
    expect($user->refresh()->last_seen_at->equalTo($seen))->toBeTrue();

    $this->travel(4)->minutes();
    actingAs($user)->get(route('home'));
    expect($user->refresh()->last_seen_at->equalTo(now()))->toBeTrue();
});

it('leaves guests alone', function () {
    get(route('home'))->assertOk();
});
