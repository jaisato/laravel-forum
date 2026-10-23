<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The member the token belongs to (/api/v1/me): the only place the API
 * shows an email address.
 */
final class MeController extends Controller
{
    public function show(Request $request): AccountResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AccountResource($user);
    }

    public function update(UpdateAccountRequest $request, UpdateProfile $updateProfile): AccountResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AccountResource($updateProfile->handle($user, $request->profile()));
    }
}
