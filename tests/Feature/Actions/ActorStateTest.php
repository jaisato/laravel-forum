<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\ReactToPost;
use App\Actions\Forum\ReplyToThread;
use App\Actions\Forum\UnmarkSolution;
use App\Actions\Forum\UpdatePost;
use App\Actions\Forum\UpdateThread;
use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\ReportPost;
use App\Enums\ReactionType;
use App\Enums\ReportReason;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Every action a member takes checks its rules again under lock, on the
 * member as stored now: a moderator may have banned them while their
 * request ran, after it was authorised.
 *
 * Each scenario gets the member and returns what they do, as a closure of
 * the copy their request loaded.
 */
it('refuses members banned after their request was authorised', function (Closure $scenario) {
    $member = User::factory()->create();
    $act = $scenario($member);
    $loaded = User::query()->findOrFail($member->id);

    app(BanUser::class)->handle($member, User::factory()->moderator()->create(), 'Spam');

    expect(fn () => $act($loaded))->toThrow(AuthorizationException::class);
})->with([
    'reply' => [fn (User $member): Closure => fn (User $as) => app(ReplyToThread::class)->handle(forumThread(), $as, 'Hola a todos.')],
    'delete their thread' => [function (User $member): Closure {
        $thread = forumThread($member);

        return fn (User $as) => app(DeleteThread::class)->handle($thread, $as);
    }],
    'edit their thread' => [function (User $member): Closure {
        $thread = forumThread($member);

        return fn (User $as) => app(UpdateThread::class)->handle($thread, $as, 'Un título nuevo para el hilo', []);
    }],
    'choose the solution' => [function (User $member): Closure {
        $thread = forumThread($member);
        $answer = forumReply($thread);

        return fn (User $as) => app(MarkSolution::class)->handle($thread, $answer, $as);
    }],
    'unmark the solution' => [function (User $member): Closure {
        $thread = forumThread($member);
        app(MarkSolution::class)->handle($thread, forumReply($thread), $member);

        return fn (User $as) => app(UnmarkSolution::class)->handle($thread->fresh(), $as);
    }],
    'edit their reply' => [function (User $member): Closure {
        $reply = forumReply(forumThread(), $member);

        return fn (User $as) => app(UpdatePost::class)->handle($reply, $as, 'Texto corregido.');
    }],
    'delete their reply' => [function (User $member): Closure {
        $reply = forumReply(forumThread(), $member);

        return fn (User $as) => app(DeletePost::class)->handle($reply, $as);
    }],
    'react' => [function (User $member): Closure {
        $reply = forumReply(forumThread());

        return fn (User $as) => app(ReactToPost::class)->toggle($reply, $as, ReactionType::Like);
    }],
    'report' => [function (User $member): Closure {
        $reply = forumReply(forumThread());

        return fn (User $as) => app(ReportPost::class)->handle($reply, $as, ReportReason::Spam);
    }],
]);
