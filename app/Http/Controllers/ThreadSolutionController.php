<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Forum\MarkSolution;
use App\Actions\Forum\UnmarkSolution;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\MarkSolutionRequest;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ThreadSolutionController extends Controller
{
    use FlashesToasts;

    public function store(MarkSolutionRequest $request, Thread $thread, MarkSolution $markSolution): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $markSolution->handle($thread, $request->answer(), $actor);
        } catch (InvalidArgumentException) {
            // Hidden meanwhile: validation still saw it published.
            throw ValidationException::withMessages(['post_id' => __('This reply cannot be the solution.')]);
        }

        $this->toast('Marked as the solution.');

        return back();
    }

    public function destroy(Request $request, Thread $thread, UnmarkSolution $unmarkSolution): RedirectResponse
    {
        Gate::authorize('view', $thread);
        Gate::authorize('markSolution', $thread);

        /** @var User $actor */
        $actor = $request->user();
        $unmarkSolution->handle($thread, $actor);

        $this->toast('Solution unmarked.');

        return back();
    }
}
