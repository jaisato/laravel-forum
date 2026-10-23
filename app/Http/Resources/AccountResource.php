<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's own account as the API shows it to them (GET /api/v1/me and
 * the new token's response): their public profile and what only they see,
 * their email, standing, language and unread counters.
 *
 * @mixin User
 */
final class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        // UserProfileResource and CurrentUserResource, written out so the
        // API documentation knows every field.
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role->value,
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => UserProfileResource::website($user->website),
            'reputation' => $user->reputation,
            'threads_count' => $user->threads_count,
            'posts_count' => $user->posts_count,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'email' => $user->email,
            'is_staff' => $user->isStaff(),
            'is_banned' => $user->isBanned(),
            'email_verified' => $user->hasVerifiedEmail(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'unread_conversations' => $user->unreadConversationsCount(),
            'locale' => $user->locale,
        ];
    }
}
