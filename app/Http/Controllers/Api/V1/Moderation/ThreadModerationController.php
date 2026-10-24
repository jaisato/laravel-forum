<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Actions\Forum\RestoreThread;
use App\Actions\Moderation\LockThread;
use App\Actions\Moderation\MoveThread;
use App\Actions\Moderation\PinThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveThreadRequest;
use App\Http\Resources\ThreadResource;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Thread moderation (staff, with the moderate ability): pinning and locking
 * (PUT sets, DELETE undoes, both idempotent), moving and restoring. Every
 * answer is the thread as it stands.
 */
final class ThreadModerationController extends Controller
{
    public function pin(Request $request, Thread $thread, PinThread $pinThread): ThreadResource
    {
        return $this->pinning($request, $thread, $pinThread, true);
    }

    public function unpin(Request $request, Thread $thread, PinThread $pinThread): ThreadResource
    {
        return $this->pinning($request, $thread, $pinThread, false);
    }

    public function lock(Request $request, Thread $thread, LockThread $lockThread): ThreadResource
    {
        return $this->locking($request, $thread, $lockThread, true);
    }

    public function unlock(Request $request, Thread $thread, LockThread $lockThread): ThreadResource
    {
        return $this->locking($request, $thread, $lockThread, false);
    }

    public function move(MoveThreadRequest $request, Thread $thread, MoveThread $moveThread): ThreadResource
    {
        $moveThread->handle($thread, $request->category(), $this->moderator($request));

        return ThreadResource::single($thread->refresh(), $request->user());
    }

    public function restore(Request $request, Thread $thread, RestoreThread $restoreThread): ThreadResource
    {
        Gate::authorize('restore', $thread);

        $restoreThread->handle($thread, $this->moderator($request));

        return ThreadResource::single($thread->refresh(), $request->user());
    }

    private function pinning(Request $request, Thread $thread, PinThread $pinThread, bool $pinned): ThreadResource
    {
        Gate::authorize('pin', $thread);

        $pinThread->handle($thread, $this->moderator($request), $pinned);

        return ThreadResource::single($thread, $request->user());
    }

    private function locking(Request $request, Thread $thread, LockThread $lockThread, bool $locked): ThreadResource
    {
        Gate::authorize('lock', $thread);

        $lockThread->handle($thread, $this->moderator($request), $locked);

        return ThreadResource::single($thread, $request->user());
    }

    private function moderator(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
