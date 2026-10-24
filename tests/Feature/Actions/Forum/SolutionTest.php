<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\RestorePost;
use App\Actions\Forum\UnmarkSolution;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\LockThread;
use App\Events\SolutionMarked;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

it('refuses a reply that the public cannot see', function (Closure $hide) {
    $thread = forumThread();
    $answer = forumReply($thread);
    $hide($answer);

    expect(fn () => app(MarkSolution::class)->handle($thread, $answer->fresh(), $thread->author))
        ->toThrow(InvalidArgumentException::class);

    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
})->with([
    // A Closure-typed parameter receives these closures as they are.
    'hidden by a moderator' => fn (Post $post) => app(HidePost::class)->handle($post, User::factory()->moderator()->create()),
    'held by the AI for review' => fn (Post $post) => app(HidePost::class)->handle($post, null),
]);

it('stops solving the thread once its answer is hidden', function (bool $byModerator) {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);

    app(HidePost::class)->handle($answer, $byModerator ? User::factory()->moderator()->create() : null);

    // Like a deleted answer: the thread is unsolved and the bonus goes back.
    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($thread->fresh()->isSolved())->toBeFalse()
        ->and($answer->author->fresh()->reputation)->toBe(0);
})->with(['by a moderator' => true, 'by the AI' => false]);

it('solves the thread again when the staff approve an answer the AI held', function () {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(HidePost::class)->handle($answer, null);

    app(ApprovePost::class)->handle($answer, User::factory()->moderator()->create());

    // The AI was wrong: the answer solves the thread again, with its bonus.
    expect($thread->fresh()->solution_post_id)->toBe($answer->id)
        ->and($answer->author->fresh()->reputation)->toBe(15);
});

it('leaves the solution to the thread author once they chose another answer meanwhile', function (bool $thenUnmarked) {
    $thread = forumThread();
    $answer = forumReply($thread);
    $other = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(HidePost::class)->handle($answer, null);
    app(MarkSolution::class)->handle($thread->fresh(), $other, $thread->author);

    if ($thenUnmarked) {
        app(UnmarkSolution::class)->handle($thread->fresh(), $thread->author);
    }

    app(ApprovePost::class)->handle($answer, User::factory()->moderator()->create());

    expect($thread->fresh()->solution_post_id)->toBe($thenUnmarked ? null : $other->id)
        ->and($answer->author->fresh()->reputation)->toBe(0)
        ->and($other->author->fresh()->reputation)->toBe($thenUnmarked ? 0 : 15);
})->with(['another one marked' => false, 'another one marked, then unmarked' => true]);

it('leaves to the thread author an answer a moderator hid, even once it is published again', function (bool $heldFirst) {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    $moderator = User::factory()->moderator()->create();

    if ($heldFirst) {
        app(HidePost::class)->handle($answer, null);
    }

    app(HidePost::class)->handle($answer, $moderator);
    app(ApprovePost::class)->handle($answer, $moderator);

    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
})->with(['hidden at once' => false, 'held by the AI, then hidden' => true]);

it('lets an answer the AI held solve nothing once deleted, even if the staff approve it', function (Closure $delete) {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(HidePost::class)->handle($answer, null);
    // The staff's approval loaded the answer before its author deleted it.
    $loaded = Post::query()->findOrFail($answer->id);

    $delete($answer);
    app(ApprovePost::class)->handle($loaded, User::factory()->moderator()->create());

    // As with the solution itself (DeletePost): a deleted answer solves
    // nothing and earns no bonus.
    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($thread->fresh()->held_solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
})->with([
    'deleted by its author' => fn (Post $answer) => app(DeletePost::class)->handle($answer, $answer->author),
    // Whatever deleted it: approving never puts the mark on a deleted post.
    'deleted any other way' => fn (Post $answer) => $answer->delete(),
]);

it('does not solve the thread again with a held answer deleted and then restored', function () {
    $thread = forumThread();
    $answer = forumReply($thread);
    app(MarkSolution::class)->handle($thread, $answer, $thread->author);
    app(HidePost::class)->handle($answer, null);
    $moderator = User::factory()->moderator()->create();

    app(DeletePost::class)->handle($answer, $answer->author);
    app(RestorePost::class)->handle(Post::withTrashed()->findOrFail($answer->id), $moderator);
    app(ApprovePost::class)->handle($answer, $moderator);

    // Deleting it settled it, as for a solution: the thread author decides.
    expect($thread->fresh()->solution_post_id)->toBeNull()
        ->and($answer->author->fresh()->reputation)->toBe(0);
});

it('refuses the author once the thread was locked after the request was authorised', function () {
    $thread = forumThread();
    $answer = forumReply($thread);
    $moderator = User::factory()->moderator()->create();
    // The copy the request authorised the change on, before a moderator locked it.
    $authorised = Thread::query()->findOrFail($thread->id);
    app(LockThread::class)->handle($thread, $moderator);

    expect(fn () => app(MarkSolution::class)->handle($authorised, $answer, $thread->author))
        ->toThrow(AuthorizationException::class);
    expect($thread->fresh()->solution_post_id)->toBeNull();

    // Staff may still choose it, and the author may no longer undo it.
    app(MarkSolution::class)->handle($authorised, $answer, $moderator);

    expect(fn () => app(UnmarkSolution::class)->handle($authorised, $thread->author))
        ->toThrow(AuthorizationException::class);
    expect($thread->fresh()->solution_post_id)->toBe($answer->id);
});
