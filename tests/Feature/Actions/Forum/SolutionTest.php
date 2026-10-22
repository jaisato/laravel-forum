<?php

declare(strict_types=1);

use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\UnmarkSolution;
use App\Events\SolutionMarked;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('marks a reply as the solution and rewards its author', function () {
    $thread = forumThread();
    $answer = forumReply($thread);

    app(MarkSolution::class)->handle($thread, $answer, $thread->author);

    expect($thread->fresh()->solution_post_id)->toBe($answer->id)
        ->and($thread->fresh()->isSolved())->toBeTrue()
        ->and($answer->author->fresh()->reputation)->toBe(15);
});

it('rewards a solution only once', function () {
    $thread = forumThread();
    $answer = forumReply($thread);

    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);

    expect($answer->author->fresh()->reputation)->toBe(15);
});

it('moves the reward when another reply becomes the solution', function () {
    $thread = forumThread();
    $first = forumReply($thread);
    $better = forumReply($thread);

    app(MarkSolution::class)->handle($thread, $first, $thread->author);
    app(MarkSolution::class)->handle($thread, $better, $thread->author);

    expect($thread->fresh()->solution_post_id)->toBe($better->id)
        ->and($first->author->fresh()->reputation)->toBe(0)
        ->and($better->author->fresh()->reputation)->toBe(15);
});

it('takes the reward back when the solution is unmarked', function () {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);

    app(UnmarkSolution::class)->handle($thread, $thread->author);
    app(UnmarkSolution::class)->handle($thread, $thread->author);

    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
});

it('does not reward authors who answer their own question', function () {
    $author = User::factory()->create();
    $thread = forumThread($author);
    $ownAnswer = forumReply($thread, $author);

    app(MarkSolution::class)->handle($thread, $ownAnswer, $author);
    app(UnmarkSolution::class)->handle($thread, $author);

    expect($author->fresh()->reputation)->toBe(0);
});

it('only accepts a reply of the same thread as solution', function () {
    $thread = forumThread();
    $elsewhere = forumReply(forumThread());

    expect(fn () => app(MarkSolution::class)->handle($thread, $thread->firstPost, $thread->author))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(MarkSolution::class)->handle($thread, $elsewhere, $thread->author))
        ->toThrow(InvalidArgumentException::class);
});

it('announces the solution once committed', function () {
    Event::fake([SolutionMarked::class]);
    $thread = forumThread();
    $answer = forumReply($thread);

    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);

    Event::assertDispatchedTimes(SolutionMarked::class, 1);
    Event::assertDispatched(SolutionMarked::class, fn (SolutionMarked $event): bool => $event->thread->is($thread)
        && $event->post->is($answer)
        && $event->actor->is($thread->author));
});
