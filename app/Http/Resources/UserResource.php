<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member next to their content. Never the email: only its owner sees it
 * (CurrentUserResource). Other resources embed it resolved, as a plain
 * array: Inertia would wrap a nested resource in "data".
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * The author of some content, or null when the account was erased: the
     * content stays and the interface credits it to «Deleted user».
     */
    public static function orErased(?User $user): ?self
    {
        return $user === null || $user->trashed() ? null : new self($user);
    }

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
            'avatar_url' => $user->avatar_url,
            'role' => $user->role->value,
        ];
    }
}
