<?php

declare(strict_types=1);

namespace App\Http\Controllers\Moderation;

use App\Actions\Forum\RestoreThread;
use App\Actions\Moderation\LockThread;
use App\Actions\Moderation\MoveThread;
use App\Actions\Moderation\PinThread;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveThreadRequest;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Inline thread moderation from the thread page (staff only, see
 * ThreadPolicy).
 */
final class ThreadModerationController extends Controller
{
    use FlashesToasts;

    public function pin(Request $request, Thread $thread, PinThread $pinThread): RedirectResponse
    {
        $this->pinning($request, $thread, $pinThread, true);

        $this->toast('Thread pinned.');

        return back();
    }

    public function unpin(Request $request, Thread $thread, PinThread $pinThread): RedirectResponse
    {
        $this->pinning($request, $thread, $pinThread, false);

        $this->toast('Thread unpinned.');

        return back();
    }

    public function lock(Request $request, Thread $thread, LockThread $lockThread): RedirectResponse
    {
        $this->locking($request, $thread, $lockThread, true);

        $this->toast('Thread locked.');

        return back();
    }

    public function unlock(Request $request, Thread $thread, LockThread $lockThread): RedirectResponse
    {
        $this->locking($request, $thread, $lockThread, false);

        $this->toast('Thread unlocked.');

        return back();
    }

    public function move(MoveThreadRequest $request, Thread $thread, MoveThread $moveThread): RedirectResponse
    {
        $moveThread->handle($thread, $request->category(), $this->moderator($request));

        $this->toast('Thread moved.');

        return back();
    }

    public function restore(Request $request, Thread $thread, RestoreThread $restoreThread): RedirectResponse
    {
        Gate::authorize('restore', $thread);

        $restoreThread->handle($thread, $this->moderator($request));

        $this->toast('Thread restored.');

        return to_route('threads.show', $thread);
    }

    private function pinning(Request $request, Thread $thread, PinThread $pinThread, bool $pinned): void
    {
        Gate::authorize('pin', $thread);

        $pinThread->handle($thread, $this->moderator($request), $pinned);
    }

    private function locking(Request $request, Thread $thread, LockThread $lockThread, bool $locked): void
    {
        Gate::authorize('lock', $thread);

        $lockThread->handle($thread, $this->moderator($request), $locked);
    }

    private function moderator(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
