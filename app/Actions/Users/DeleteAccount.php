<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\ThreadRead;
use App\Models\ThreadSubscription;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Erases a member on request (spec §4.1): their personal data and their
 * ways in are gone, while their posts stay, credited to «Deleted user».
 * The account row survives as a soft deleted shell so the posts keep
 * their author, and the email and username become free again.
 */
final class DeleteAccount
{
    public function handle(User $user): void
    {
        $avatar = $user->avatar_path;

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->passkeys()->delete();
            $user->notifications()->delete();
            // Those it caused for others name it in their title and payload.
            DatabaseNotification::query()->where('actor_id', $user->id)->delete();
            ThreadSubscription::query()->where('user_id', $user->id)->delete();
            ThreadRead::query()->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => '',
                'username' => "deleted-{$user->id}",
                'email' => "deleted-{$user->id}@deleted.invalid",
                'email_verified_at' => null,
                // Nobody knows it, so the shell can never be signed into.
                'password' => Str::random(64),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'avatar_path' => null,
                'bio' => null,
                'location' => null,
                'website' => null,
                'locale' => null,
                'preferences' => [],
                'last_seen_at' => null,
            ])->save();

            $user->delete();
        });

        // After the commit: a rolled back erasure keeps its picture.
        if ($avatar !== null) {
            Storage::disk(config('forum.profiles.avatar.disk'))->delete($avatar);
        }
    }
}
