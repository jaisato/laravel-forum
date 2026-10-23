<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesToasts;
use App\Models\Thread;
use App\Models\User;
use App\Services\ThreadSubscriptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ThreadSubscriptionController extends Controller
{
    use FlashesToasts;

    public function store(Request $request, Thread $thread, ThreadSubscriptions $subscriptions): RedirectResponse
    {
        Gate::authorize('subscribe', $thread);

        /** @var User $member */
        $member = $request->user();
        $subscriptions->subscribe($thread, $member);

        $this->toast('You are following this thread.');

        return back();
    }

    public function destroy(Request $request, Thread $thread, ThreadSubscriptions $subscriptions): RedirectResponse
    {
        Gate::authorize('subscribe', $thread);

        /** @var User $member */
        $member = $request->user();
        $subscriptions->unsubscribe($thread, $member);

        $this->toast('You no longer follow this thread.');

        return back();
    }
}
