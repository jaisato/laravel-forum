<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The member's API tokens (spec §4.1): created with abilities, shown in
 * clear only once (flash data "token") and revoked at will.
 */
final class ApiTokenController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $user->tokens()
                ->latest('id')
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ])
                ->all(),
            'abilities' => $user->tokenAbilities(),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken($request->string('name')->value(), $request->abilities());

        Inertia::flash('token', $token->plainTextToken);
        $this->toast('Token created. Copy it now: it will not be shown again.');

        return back();
    }

    /**
     * Only the member's own tokens are found.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->tokens()->whereKey($token)->firstOrFail()->delete();

        $this->toast('Token revoked.');

        return back();
    }
}
