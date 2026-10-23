<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed in member as every page sees it (shared Inertia prop
 * auth.user): identity, standing and the counters of the header.
 *
 * @mixin User
 */
final class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role->value,
            'is_staff' => $user->isStaff(),
            'is_banned' => $user->isBanned(),
            'email_verified' => $user->hasVerifiedEmail(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'unread_conversations' => $user->unreadConversationsCount(),
        ];
    }
}
