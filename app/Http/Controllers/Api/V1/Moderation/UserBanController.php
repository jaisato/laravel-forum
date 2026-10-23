<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\UnbanUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BanUserRequest;
use App\Http\Resources\BanResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Bans from the moderation API (spec §4.5), as from the panel: moderators
 * ban members, administrators anyone but administrators (UserPolicy::ban).
 * PUT bans, or changes the ban (no date: for good), and revokes the
 * member's tokens; DELETE lifts it, and lifting none changes nothing.
 */
final class UserBanController extends Controller
{
    public function update(BanUserRequest $request, User $user, BanUser $banUser): BanResource
    {
        $banUser->handle($user, $this->moderator($request), $request->reason(), $request->until());

        return new BanResource($user->refresh());
    }

    public function destroy(Request $request, User $user, UnbanUser $unbanUser): Response
    {
        Gate::authorize('ban', $user);

        $unbanUser->handle($user, $this->moderator($request));

        return response()->noContent();
    }

    private function moderator(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
