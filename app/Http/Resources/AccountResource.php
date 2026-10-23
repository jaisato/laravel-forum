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

        return [
            ...(new UserProfileResource($user))->resolve($request),
            ...(new CurrentUserResource($user))->resolve($request),
            'locale' => $user->locale,
        ];
    }
}
