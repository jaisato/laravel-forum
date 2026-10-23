<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\User;
use App\Services\ThreadSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Following a thread (PUT) and leaving it (DELETE), both idempotent.
 */
final class ThreadSubscriptionController extends Controller
{
    public function update(Request $request, Thread $thread, ThreadSubscriptions $subscriptions): Response
    {
        $subscriptions->subscribe($thread, $this->member($request, $thread));

        return response()->noContent();
    }

    public function destroy(Request $request, Thread $thread, ThreadSubscriptions $subscriptions): Response
    {
        $subscriptions->unsubscribe($thread, $this->member($request, $thread));

        return response()->noContent();
    }

    /**
     * Threads the member may not see answer 404 before the rule is asked.
     */
    private function member(Request $request, Thread $thread): User
    {
        Gate::authorize('view', $thread);
        Gate::authorize('subscribe', $thread);

        /** @var User */
        return $request->user();
    }
}
