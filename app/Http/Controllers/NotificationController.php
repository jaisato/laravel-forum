<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notifications page (spec §4.4): the member's notifications, newest
 * first, opened one at a time or all marked read at once, and the unread
 * count the bell polls between visits.
 */
final class NotificationController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $notifications = $this->notificationsOf($request)
            ->paginate((int) config('forum.pagination.notifications'));

        return Inertia::render('notifications/Index', [
            'notifications' => NotificationResource::collection($notifications),
        ]);
    }

    /**
     * Opens a notification: marks it read and goes where it leads, or back
     * to the list when it no longer leads anywhere. Only the member's own
     * notifications are found.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();
        /** @var UserNotification $opened */
        $opened = $this->notificationsOf($request)->findOrFail($notification);

        $opened->markAsRead();

        $path = $opened->isWithheldFrom($member) ? null : $opened->path();

        return redirect($path ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();

        $member->unreadNotifications()->update(['read_at' => now()]);

        $this->toast('All notifications marked as read.');

        return back();
    }

    /**
     * How many notifications are unread: the bell asks every now and then,
     * so it is right between visits too.
     */
    public function unread(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        return response()->json(['count' => $member->unreadNotifications()->count()]);
    }

    /**
     * The member's notifications with what NotificationResource needs to
     * tell whether their posts are still visible.
     *
     * @return MorphMany<UserNotification, User>
     */
    private function notificationsOf(Request $request): MorphMany
    {
        /** @var User $member */
        $member = $request->user();

        return $member->notifications()->with([
            'post.thread' => fn (BelongsTo $threads) => $threads->withTrashed(),
        ]);
    }
}
