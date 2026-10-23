<?php

declare(strict_types=1);

use App\Actions\Forum\ReactToPost;
use App\Actions\Moderation\LockThread;
use App\Enums\ReactionType;
use App\Events\ReactionAdded;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

function reactions(): ReactToPost
{
    return app(ReactToPost::class);
}

it('adds a reaction once and rewards the author once', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    expect(reactions()->add($post, $member, ReactionType::Like))->toBeTrue()
        ->and(reactions()->add($post, $member, ReactionType::Like))->toBeFalse()
        ->and($post->reactions()->count())->toBe(1)
        ->and($post->author->fresh()->reputation)->toBe(1);
});

it('counts each reaction type separately', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    reactions()->add($post, $member, ReactionType::Like);
    reactions()->add($post, $member, ReactionType::Insightful);

    // Enum cases cannot be ordered, so compare their values.
    expect($post->reactions()->pluck('type')->map(fn (ReactionType $type): string => $type->value)->sort()->values()->all())
        ->toBe(['insightful', 'like'])
        ->and($post->author->fresh()->reputation)->toBe(2);
});

it('removes a reaction and takes its reputation back', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();
    reactions()->add($post, $member, ReactionType::Love);

    expect(reactions()->remove($post, $member, ReactionType::Love))->toBeTrue()
        ->and($post->reactions()->count())->toBe(0)
        ->and($post->author->fresh()->reputation)->toBe(0);
});

it('does not take reputation back for a reaction that was never there', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    expect(reactions()->remove($post, $member, ReactionType::Love))->toBeFalse()
        ->and($post->author->fresh()->reputation)->toBe(0);
});

it('toggles a reaction on and off', function () {
    $post = Post::factory()->create();
    $member = User::factory()->create();

    expect(reactions()->handle($post, $member, ReactionType::Celebrate))->toBeTrue()
        ->and(reactions()->handle($post, $member, ReactionType::Celebrate))->toBeFalse()
        ->and($post->reactions()->count())->toBe(0)
        ->and($post->author->fresh()->reputation)->toBe(0);
});

it('announces only reactions that were really added', function () {
    Event::fake([ReactionAdded::class]);
    $post = Post::factory()->create();
    $member = User::factory()->create();

    reactions()->add($post, $member, ReactionType::Laugh);
    reactions()->add($post, $member, ReactionType::Laugh);

    Event::assertDispatchedTimes(ReactionAdded::class, 1);
    Event::assertDispatched(ReactionAdded::class, fn (ReactionAdded $event): bool => $event->post->is($post)
        && $event->user->is($member)
        && $event->type === ReactionType::Laugh);
});

it('refuses the reaction once the thread was locked after the request was authorised', function () {
    $reply = forumReply(forumThread());
    // The copy the request authorised the reaction on, before a moderator locked the thread.
    $authorised = Post::query()->findOrFail($reply->id);
    app(LockThread::class)->handle($reply->thread, User::factory()->moderator()->create());

    expect(fn () => app(ReactToPost::class)->handle($authorised, User::factory()->create(), ReactionType::Like))
        ->toThrow(AuthorizationException::class);
    expect(Reaction::query()->count())->toBe(0);
});

it('gives and takes back a reaction whatever its state, never twice', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();

    expect(reactions()->give($reply, $member, ReactionType::Like))->toBeTrue()
        ->and(reactions()->give($reply, $member, ReactionType::Like))->toBeFalse()
        ->and($reply->author->fresh()->reputation)->toBe(1)
        ->and(reactions()->takeBack($reply, $member, ReactionType::Like))->toBeTrue()
        ->and(reactions()->takeBack($reply, $member, ReactionType::Like))->toBeFalse()
        ->and($reply->author->fresh()->reputation)->toBe(0);
});

it('neither gives nor takes back once the thread was locked after the request was authorised', function () {
    $reply = forumReply(forumThread());
    $member = User::factory()->create();
    reactions()->give($reply, $member, ReactionType::Love);
    $authorised = Post::query()->findOrFail($reply->id);
    app(LockThread::class)->handle($reply->thread, User::factory()->moderator()->create());

    expect(fn () => reactions()->give($authorised, $member, ReactionType::Like))->toThrow(AuthorizationException::class)
        ->and(fn () => reactions()->takeBack($authorised, $member, ReactionType::Love))->toThrow(AuthorizationException::class);
    expect(Reaction::query()->pluck('type')->map->value->all())->toBe(['love']);
});
