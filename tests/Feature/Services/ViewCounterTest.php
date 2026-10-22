<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\User;
use App\Services\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

function threadVisit(?User $user = null, string $session = 'session-a'): Request
{
    $request = Request::create('/t/a-thread');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(new Store('forum', new ArraySessionHandler(120), sha1($session)));

    return $request;
}

it('counts the first visit of a member and ignores repeats within the hour', function () {
    $thread = Thread::factory()->create();
    $member = User::factory()->create();
    $counter = app(ViewCounter::class);

    expect($counter->record($thread, threadVisit($member)))->toBeTrue()
        ->and($counter->record($thread, threadVisit($member, 'another-session')))->toBeFalse()
        ->and($thread->fresh()->views_count)->toBe(1)
        ->and($thread->views_count)->toBe(1);

    $this->travel(61)->minutes();

    expect($counter->record($thread, threadVisit($member)))->toBeTrue()
        ->and($thread->fresh()->views_count)->toBe(2);
});

it('tells guests apart by their session', function () {
    $thread = Thread::factory()->create();
    $counter = app(ViewCounter::class);

    $counter->record($thread, threadVisit(session: 'guest-1'));
    $counter->record($thread, threadVisit(session: 'guest-1'));
    $counter->record($thread, threadVisit(session: 'guest-2'));

    expect($thread->fresh()->views_count)->toBe(2);
});

it('counts views without touching the last update of the thread', function () {
    $thread = Thread::factory()->create();
    $updatedAt = $thread->fresh()->updated_at;
    $this->travel(1)->hour();

    app(ViewCounter::class)->record($thread, threadVisit(User::factory()->create()));

    expect($thread->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
