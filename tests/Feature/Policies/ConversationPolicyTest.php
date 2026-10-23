<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

function conversationBetween(User ...$users): Conversation
{
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach(array_map(fn (User $user): int => $user->id, $users));

    return $conversation;
}

it('opens a conversation to its active participants only', function () {
    [$alice, $bob, $outsider] = User::factory()->count(3)->create();
    $conversation = conversationBetween($alice, $bob);

    expect(allows($alice, 'view', $conversation))->toBeTrue()
        ->and(allows($alice, 'reply', $conversation))->toBeTrue()
        ->and(allows($alice, 'leave', $conversation))->toBeTrue()
        ->and(allows($outsider, 'view', $conversation))->toBeFalse()
        ->and(allows(User::factory()->admin()->create(), 'view', $conversation))->toBeFalse();
});

it('closes a conversation to a participant who left it', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $conversation = conversationBetween($alice, $bob);
    $conversation->participants()->updateExistingPivot($bob->id, ['left_at' => now()]);

    expect(allows($bob, 'view', $conversation))->toBeFalse()
        ->and(allows($bob, 'reply', $conversation))->toBeFalse()
        ->and(allows($bob, 'leave', $conversation))->toBeFalse();
});

it('lets banned participants read but not reply', function () {
    $alice = User::factory()->create();
    $banned = User::factory()->banned()->create();
    $conversation = conversationBetween($alice, $banned);

    expect(allows($banned, 'view', $conversation))->toBeTrue()
        ->and(allows($banned, 'reply', $conversation))->toBeFalse();
});

it('lets members start conversations with other members', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'message', [Conversation::class, User::factory()->create()]))->toBe($expected);
})->with([
    ['guest', false],
    ['unverified', false],
    ['banned', false],
    ['member', true],
    ['moderator', true],
]);

it('does not let members write to themselves', function () {
    $member = User::factory()->create();

    expect(allows($member, 'message', [Conversation::class, $member]))->toBeFalse();
});

it('respects members who turned private messages off, except for staff', function () {
    $recipient = User::factory()->create(['preferences' => ['allow_private_messages' => false]]);

    expect(allows(User::factory()->create(), 'message', [Conversation::class, $recipient]))->toBeFalse()
        ->and(allows(User::factory()->moderator()->create(), 'message', [Conversation::class, $recipient]))->toBeTrue();
});
