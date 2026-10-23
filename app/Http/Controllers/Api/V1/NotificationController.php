<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The member's notifications, newest first (?unread=1 for the unread ones
 * alone), and marking them read. They are the member's own state, so the
 * read ability is enough: a token that forwards them elsewhere need not be
 * able to post.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $unread = $request->boolean('unread');

        $notifications = $this->member($request)->notifications()->withPost()
            ->when($unread, fn (Builder $notifications) => $notifications->whereNull('read_at'))
            ->paginate((int) config('forum.pagination.notifications'))
            ->appends(array_filter(['unread' => $unread ? 1 : null]));

        return NotificationResource::collection($notifications);
    }

    /**
     * Marks one read; only the member's own notifications are found.
     */
    public function read(Request $request, string $notification): NotificationResource
    {
        /** @var UserNotification $opened */
        $opened = $this->member($request)->notifications()->withPost()->findOrFail($notification);

        $opened->markAsRead();

        return new NotificationResource($opened);
    }

    public function readAll(Request $request): Response
    {
        $this->member($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function member(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
