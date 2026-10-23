<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Users\DeleteAccount;
use App\Actions\Users\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'status' => $request->session()->get('status'),
            // The name and the email come with every page (auth.user).
            'profile' => [
                'username' => $user->username,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'locale' => $user->locale,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateProfile->handle($user, $request->profile(), $request->file('avatar'), $request->boolean('remove_avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Erase the member's account: personal data goes, posts stay.
     */
    public function destroy(ProfileDeleteRequest $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $deleteAccount->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
