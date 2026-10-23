<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\ChangeUserRole;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\ReportPost;
use App\Enums\PostStatus;
use App\Enums\ReportReason;
use App\Enums\Role;
use App\Models\Category;
use App\Models\User;

/**
 * A token of a moderator, with the moderate ability.
 */
function moderatorToken(?User $moderator = null): string
{
    return apiToken($moderator ?? User::factory()->moderator()->create(), ['read', 'moderate']);
}

it('is for staff, with a token that has the moderate ability', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();

    $this->putJson("/api/v1/moderation/threads/{$thread->id}/pin")->assertUnauthorized();

    // Members cannot give a token that ability (POST /auth/token), and
    // staff tokens without it do not moderate.
    $this->withToken(apiToken(User::factory()->create()))
        ->putJson("/api/v1/moderation/threads/{$thread->id}/pin")
        ->assertForbidden();

    $this->withToken(apiToken($moderator, ['read', 'write']))
        ->putJson("/api/v1/moderation/threads/{$thread->id}/pin")
        ->assertForbidden();

    // Staff powers end with the role, whatever the token says.
    $former = User::factory()->moderator()->create();
    $token = moderatorToken($former);
    app(ChangeUserRole::class)->handle($former, User::factory()->admin()->create(), Role::Member);

    $this->withToken($token)
        ->putJson("/api/v1/moderation/threads/{$thread->id}/pin")
        ->assertForbidden();

    expect($thread->fresh()->is_pinned)->toBeFalse();
});

it('pins and locks threads, and undoes it, however many times it is sent', function () {
    $thread = forumThread();
    $token = moderatorToken();

    foreach (range(1, 2) as $attempt) {
        $this->withToken($token)
            ->putJson("/api/v1/moderation/threads/{$thread->id}/pin")
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true);

        $this->withToken($token)
            ->putJson("/api/v1/moderation/threads/{$thread->id}/lock")
            ->assertOk()
            ->assertJsonPath('data.is_locked', true);
    }

    $this->withToken($token)
        ->deleteJson("/api/v1/moderation/threads/{$thread->id}/pin")
        ->assertJsonPath('data.is_pinned', false);

    $this->withToken($token)
        ->deleteJson("/api/v1/moderation/threads/{$thread->id}/lock")
        ->assertJsonPath('data.is_locked', false);
});

it('moves threads to another category', function () {
    $thread = forumThread();
    $target = Category::factory()->create();
    $token = moderatorToken();

    $this->withToken($token)
        ->postJson("/api/v1/moderation/threads/{$thread->id}/move", ['category_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.category.id', $target->id);

    $this->withToken($token)
        ->postJson("/api/v1/moderation/threads/{$thread->id}/move", ['category_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

it('restores deleted threads', function () {
    $moderator = User::factory()->moderator()->create();
    $thread = forumThread();
    app(DeleteThread::class)->handle($thread, $moderator);

    $this->withToken(moderatorToken($moderator))
        ->postJson("/api/v1/moderation/threads/{$thread->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.is_deleted', false);

    expect($thread->fresh()->trashed())->toBeFalse();
});

it('hides, approves and restores posts', function () {
    $moderator = User::factory()->moderator()->create();
    $reply = forumReply(forumThread());
    $token = moderatorToken($moderator);

    $this->withToken($token)
        ->postJson("/api/v1/moderation/posts/{$reply->id}/hide", ['reason' => 'Fuera de tema'])
        ->assertOk()
        ->assertJsonPath('data.status', 'hidden');

    $this->withToken($token)
        ->postJson("/api/v1/moderation/posts/{$reply->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    app(DeletePost::class)->handle($reply->fresh(), $reply->author);

    $this->withToken($token)
        ->postJson("/api/v1/moderation/posts/{$reply->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.is_deleted', false);

    expect($reply->fresh())
        ->trashed()->toBeFalse()
        ->status->toBe(PostStatus::Published);
});

it('works the report queue', function () {
    $reporter = User::factory()->create(['username' => 'ana']);
    $spam = forumReply(forumThread(), body: 'Compra en mi tienda');
    $fine = forumReply(forumThread());
    $resolved = app(ReportPost::class)->handle($spam, $reporter, ReportReason::Spam, 'Enlaces a una tienda.');
    $dismissed = app(ReportPost::class)->handle($fine, $reporter, ReportReason::Abuse);
    $token = moderatorToken();

    $this->withToken($token)
        ->getJson('/api/v1/moderation/reports')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $resolved->id)
        ->assertJsonPath('data.0.reason', 'spam')
        ->assertJsonPath('data.0.details', 'Enlaces a una tienda.')
        ->assertJsonPath('data.0.status', 'open')
        ->assertJsonPath('data.0.reporter.username', 'ana')
        ->assertJsonPath('data.0.post.id', $spam->id)
        ->assertJsonPath('data.0.post.body_html', '<p>Compra en mi tienda</p>');

    $this->withToken($token)
        ->postJson("/api/v1/moderation/reports/{$resolved->id}/resolve", ['hide_post' => true, 'note' => 'Spam evidente'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution_note', 'Spam evidente');

    $this->withToken($token)
        ->postJson("/api/v1/moderation/reports/{$dismissed->id}/dismiss")
        ->assertOk()
        ->assertJsonPath('data.status', 'dismissed');

    expect($spam->fresh()->status)->toBe(PostStatus::Hidden);

    $this->withToken($token)->getJson('/api/v1/moderation/reports')->assertJsonCount(0, 'data');

    $this->withToken($token)
        ->getJson('/api/v1/moderation/reports?status=resolved')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $resolved->id);

    $this->withToken($token)
        ->getJson('/api/v1/moderation/reports?status=nada')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('reads what members may not see, to moderate it', function () {
    $moderator = User::factory()->moderator()->create();
    $reply = forumReply(forumThread());
    app(HidePost::class)->handle($reply, $moderator);

    $this->withToken(moderatorToken($moderator))
        ->postJson("/api/v1/moderation/posts/{$reply->id}/approve")
        ->assertOk();
});

it('settles the reports of posts the AI held: dismissing publishes them again, resolving keeps them out of view', function () {
    $held = function (): array {
        $reply = forumReply(forumThread());
        app(HidePost::class)->handle($reply, null, 'Posible spam');

        return [$reply, app(ReportPost::class)->forAi($reply, ReportReason::Spam, 'Posible spam', 0.92)];
    };
    [$published, $dismissed] = $held();
    [$kept, $dismissedOnly] = $held();
    [$hidden, $resolved] = $held();
    $token = moderatorToken();

    $this->withToken($token)->postJson("/api/v1/moderation/reports/{$dismissed->id}/dismiss")->assertOk();
    $this->withToken($token)->postJson("/api/v1/moderation/reports/{$dismissedOnly->id}/dismiss", ['approve_post' => false])->assertOk();
    $this->withToken($token)->postJson("/api/v1/moderation/reports/{$resolved->id}/resolve")->assertOk();

    expect($published->fresh()->status)->toBe(PostStatus::Published)
        ->and($kept->fresh()->status)->toBe(PostStatus::PendingReview)
        ->and($hidden->fresh()->status)->toBe(PostStatus::Hidden);
});

describe('bans', function () {
    it('bans a member, for good or until a date, and lifts the ban', function () {
        $member = User::factory()->create(['username' => 'ana']);
        $member->createToken('Un script', ['read']);
        $token = moderatorToken();
        $until = now()->addWeek()->startOfSecond();

        $this->withToken($token)
            ->putJson('/api/v1/moderation/users/ana/ban', ['reason' => 'Spam reiterado', 'until' => $until->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.username', 'ana')
            ->assertJsonPath('data.reason', 'Spam reiterado')
            ->assertJsonPath('data.banned_until', $until->toIso8601String());

        expect($member->fresh()->isBanned())->toBeTrue()
            ->and($member->tokens()->count())->toBe(0);

        $this->withToken($token)
            ->putJson('/api/v1/moderation/users/ana/ban', ['reason' => 'Para siempre'])
            ->assertOk()
            ->assertJsonPath('data.banned_until', null);

        $this->withToken($token)->deleteJson('/api/v1/moderation/users/ana/ban')->assertNoContent();
        $this->withToken($token)->deleteJson('/api/v1/moderation/users/ana/ban')->assertNoContent();

        expect($member->fresh()->isBanned())->toBeFalse();
    });

    it('validates the ban', function (array $sent, string $field) {
        User::factory()->create(['username' => 'ana']);

        $this->withToken(moderatorToken())
            ->putJson('/api/v1/moderation/users/ana/ban', $sent)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'no reason' => [[], 'reason'],
        'a long reason' => [['reason' => str_repeat('a', 501)], 'reason'],
        'a past date' => [['reason' => 'Spam', 'until' => '2020-01-01T00:00:00+00:00'], 'until'],
    ]);

    it('lets moderators ban members only, and nobody themselves', function () {
        $moderator = User::factory()->moderator()->create(['username' => 'mod']);
        User::factory()->moderator()->create(['username' => 'otro']);
        $token = moderatorToken($moderator);

        $this->withToken($token)->putJson('/api/v1/moderation/users/otro/ban', ['reason' => 'Spam'])->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/moderation/users/mod/ban', ['reason' => 'Spam'])->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/moderation/users/otro/ban')->assertForbidden();
        $this->withToken(apiToken(User::factory()->create()))
            ->putJson('/api/v1/moderation/users/otro/ban', ['reason' => 'Spam'])
            ->assertForbidden();
    });
});
