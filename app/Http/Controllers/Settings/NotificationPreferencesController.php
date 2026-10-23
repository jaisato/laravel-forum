<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which emails the member wants and whether they take private messages
 * (spec §4.1): notifications always reach the bell.
 */
final class NotificationPreferencesController extends Controller
{
    use FlashesToasts;

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Notifications', [
            'preferences' => $user->preferences,
        ]);
    }

    public function update(NotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['preferences' => $request->preferences()])->save();

        $this->toast('Preferences saved.');

        return to_route('notifications.preferences.edit');
    }
}
