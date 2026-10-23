<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\UnmarkSolution;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkSolutionRequest;
use App\Http\Resources\ThreadResource;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The reply that solves a thread (PUT {post_id}) and taking the mark away
 * (DELETE), both idempotent; they answer the thread.
 */
final class ThreadSolutionController extends Controller
{
    public function update(MarkSolutionRequest $request, Thread $thread, MarkSolution $markSolution): ThreadResource
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $markSolution->handle($thread, $request->answer(), $actor);
        } catch (InvalidArgumentException) {
            // Hidden meanwhile: validation still saw it published.
            throw ValidationException::withMessages(['post_id' => __('This reply cannot be the solution.')]);
        }

        return ThreadResource::single($thread->refresh());
    }

    public function destroy(Request $request, Thread $thread, UnmarkSolution $unmarkSolution): ThreadResource
    {
        Gate::authorize('view', $thread);
        Gate::authorize('markSolution', $thread);

        /** @var User $actor */
        $actor = $request->user();

        $unmarkSolution->handle($thread, $actor);

        return ThreadResource::single($thread->refresh());
    }
}
