<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\NewPrivateMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The member's notifications, newest first (?unread=1 for the unread ones
 * alone), and marking them read. They are the member's own state, so the
 * read ability is enough: a token that forwards them elsewhere need not be
 * able to post. Those about private messages need the messages ability too.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $unread = $request->boolean('unread');

        $notifications = $this->reachable($request)->withPost()
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
        $opened = $this->reachable($request)->withPost()->findOrFail($notification);

        $opened->markAsRead();

        return new NotificationResource($opened);
    }

    public function readAll(Request $request): Response
    {
        $this->reachable($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * The member's notifications this token may reach: those about private
     * messages (their subject, sender and first words) need the messages
     * ability, as the conversations themselves do.
     *
     * @return Builder<UserNotification>
     */
    private function reachable(Request $request): Builder
    {
        $member = $this->member($request);
        $notifications = $member->notifications()->getQuery();

        return $member->tokenCan('messages')
            ? $notifications
            : $notifications->where('type', '!=', NewPrivateMessage::TYPE);
    }

    private function member(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
