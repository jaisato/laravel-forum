<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Http\Resources\AccountResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API tokens for clients that sign in with the member's password (spec
 * §4.7); the settings page creates them for the signed in member too.
 */
final class AuthTokenController extends Controller
{
    /**
     * Signs the member in and gives the device named a token with the
     * abilities asked for. Who may hold one is the settings page's rule:
     * members who have verified their email and are not banned (banning
     * revokes the tokens they had).
     */
    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        abort_if($user->isBanned(), 403, __('Your account is suspended, so you cannot create API tokens.'));
        abort_unless($user->hasVerifiedEmail(), 403, __('Verify your email address before creating API tokens.'));

        $abilities = $request->abilitiesFor($user);
        $token = $user->createToken($request->string('device_name')->value(), $abilities);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'user' => (new AccountResource($user))->resolve($request),
        ], 201);
    }

    /**
     * Revokes the token the request comes with; the member's other tokens
     * keep working.
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
