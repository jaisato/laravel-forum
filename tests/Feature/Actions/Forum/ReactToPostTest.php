<?php

declare(strict_types=1);

use App\Actions\Forum\ReactToPost;
use App\Enums\ReactionType;
use App\Events\ReactionAdded;
use App\Models\Post;
use App\Models\User;
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

    expect($post->reactions()->pluck('type')->sort()->values()->all())->toBe([ReactionType::Insightful, ReactionType::Like])
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

    expect(reactions()->toggle($post, $member, ReactionType::Celebrate))->toBeTrue()
        ->and(reactions()->toggle($post, $member, ReactionType::Celebrate))->toBeFalse()
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
