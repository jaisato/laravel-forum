<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member's ban as the staff see it: since when, until when (null: for
 * good) and why.
 */
final class BanResource extends JsonResource
{
    /**
     * @return array{username: string, banned_at: string|null, banned_until: string|null, reason: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var User $member */
        $member = $this->resource;

        return [
            'username' => $member->username,
            'banned_at' => $member->banned_at?->toIso8601String(),
            'banned_until' => $member->banned_until?->toIso8601String(),
            'reason' => $member->ban_reason,
        ];
    }
}
