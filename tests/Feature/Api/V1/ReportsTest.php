<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Models\Report;
use App\Models\User;

it('files a report once while it is open, however many times it is sent', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();
    $token = apiToken($member);

    foreach (range(1, 2) as $attempt) {
        $this->withToken($token)
            ->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'spam', 'details' => 'Enlaces a una tienda.'])
            ->assertAccepted()
            ->assertJsonPath('message', 'Thanks, the moderators will review it.');
    }

    expect(Report::query()->count())->toBe(1)
        ->and(Report::query()->first())
        ->reporter_id->toBe($member->id)
        ->details->toBe('Enlaces a una tienda.');
});

it('needs a token with the write ability', function () {
    $reply = forumReply(forumThread());

    $this->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'spam'])->assertUnauthorized();

    $this->withToken(apiToken(User::factory()->create(), ['read']))
        ->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'spam'])
        ->assertForbidden();
});

it('validates the report', function () {
    $reply = forumReply(forumThread());

    $this->withToken(apiToken(User::factory()->create()))
        ->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'aburrido', 'details' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason', 'details']);
});

it('does not let members report their own posts', function () {
    $reply = forumReply(forumThread());

    $this->withToken(apiToken($reply->author))
        ->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'spam'])
        ->assertForbidden();
});

it('answers 404 to members for posts they may not see', function () {
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    $this->withToken(apiToken(User::factory()->create()))
        ->postJson("/api/v1/posts/{$reply->id}/reports", ['reason' => 'spam'])
        ->assertNotFound();

    expect(Report::query()->count())->toBe(0);
});
