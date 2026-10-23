<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's public profile (spec §4.1): who they are and how they take
 * part. Never their email, their standing (bans) or their settings.
 *
 * @mixin User
 */
final class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            ...(new UserResource($user))->resolve($request),
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => $this->website($user->website),
            'reputation' => $user->reputation,
            'threads_count' => $user->threads_count,
            'posts_count' => $user->posts_count,
            'created_at' => $user->created_at?->toIso8601String(),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }

    /**
     * Only web addresses: the profile links it, and a javascript: or data:
     * address would run in the visitor's browser.
     */
    private function website(?string $website): ?string
    {
        $scheme = $website === null ? null : parse_url($website, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) ? $website : null;
    }
}
