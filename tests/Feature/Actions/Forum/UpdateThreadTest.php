<?php

declare(strict_types=1);

use App\Actions\Forum\UpdateThread;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\LockThread;
use App\Enums\ModerationActionType;
use App\Events\ThreadUpdated;
use App\Models\ModerationAction;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

it('renames the thread but keeps its address', function () {
    $thread = forumThread();
    $slug = $thread->slug;

    $updated = app(UpdateThread::class)->handle($thread, $thread->author, 'A much better title for the thread', []);

    expect($updated->title)->toBe('A much better title for the thread')
        ->and($updated->fresh()->slug)->toBe($slug);
});

it('moves the tag counters with the tags', function () {
    [$kept, $dropped, $added] = Tag::factory()->count(3)->create();
    $thread = forumThread(tagIds: [$kept->id, $dropped->id]);

    app(UpdateThread::class)->handle($thread, $thread->author, $thread->title, [$kept->id, $added->id]);

    expect($thread->tags()->pluck('tags.id')->sort()->values()->all())->toBe(collect([$kept->id, $added->id])->sort()->values()->all())
        ->and($kept->fresh()->threads_count)->toBe(1)
        ->and($dropped->fresh()->threads_count)->toBe(0)
        ->and($added->fresh()->threads_count)->toBe(1);
});

it('logs edits made by staff on threads of others', function () {
    $thread = forumThread();
    $moderator = User::factory()->moderator()->create();
    $title = $thread->title;

    app(UpdateThread::class)->handle($thread, $moderator, 'Retitled by the moderators', []);
    app(UpdateThread::class)->handle($thread, $thread->author, 'Retitled by the author', []);

    $action = ModerationAction::query()->sole();

    expect($action->action)->toBe(ModerationActionType::ThreadEdited)
        ->and($action->moderator->is($moderator))->toBeTrue()
        ->and($action->subject->is($thread))->toBeTrue()
        ->and($action->meta)->toBe(['previous_title' => $title, 'title' => 'Retitled by the moderators']);
});

it('announces the change once committed', function () {
    Event::fake([ThreadUpdated::class]);
    $thread = forumThread();

    app(UpdateThread::class)->handle($thread, $thread->author, 'A much better title for the thread', []);

    Event::assertDispatched(ThreadUpdated::class, fn (ThreadUpdated $event): bool => $event->thread->is($thread));
});

it('decides the tag counters on the current state of the thread, not a stale copy', function () {
    $tag = Tag::factory()->create();
    $thread = forumThread(tagIds: [$tag->id]);
    // A moderator's edit form loaded the thread…
    $stale = $thread->fresh();
    // …then another moderator hid it, which took it out of the tag counters.
    // (Its author may no longer edit it at all.)
    app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

    $other = Tag::factory()->create();
    app(UpdateThread::class)->handle($stale, User::factory()->moderator()->create(), $thread->title, [$other->id]);

    expect($tag->fresh()->threads_count)->toBe(0)
        ->and($other->fresh()->threads_count)->toBe(0);

    $this->artisan('forum:recount')->expectsOutputToContain('All counters were already right');
});

it('refuses the author once the thread was locked after the request was authorised', function () {
    $thread = forumThread();
    $authorised = Thread::query()->findOrFail($thread->id);
    app(LockThread::class)->handle($thread, User::factory()->moderator()->create());

    expect(fn () => app(UpdateThread::class)->handle($authorised, $thread->author, 'Otro título distinto', []))
        ->toThrow(AuthorizationException::class);
    expect($thread->fresh()->title)->toBe($authorised->title);
});
