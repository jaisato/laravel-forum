<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A notification of the signed in member, linking to a path on this forum.
 * One about a post they may no longer see keeps its place in the list, but
 * not what it said: who, what and where (UserNotification::isWithheldFrom()).
 * Expects post.thread loaded, deleted threads included.
 *
 * @mixin UserNotification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserNotification $notification */
        $notification = $this->resource;
        /** @var User $recipient */
        $recipient = $request->user();
        $withheld = $notification->isWithheldFrom($recipient);

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $withheld ? __('This content is no longer available.') : ($notification->data['title'] ?? ''),
            'excerpt' => $withheld ? null : ($notification->data['excerpt'] ?? null),
            'url' => $withheld ? null : $notification->path(),
            'actor' => $withheld ? null : ($notification->data['actor'] ?? null),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
