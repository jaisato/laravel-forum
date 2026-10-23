<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\RetiredUsername;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Updates what a member tells about themselves: name, username, email, the
 * public profile (biography, location, website), the interface language and
 * the avatar. A username left behind is retired (RetiredUsername): nobody
 * else may take it. A new email address is unverified until its link is followed
 * (members without one cannot post), so the link goes out at once. Avatars
 * are stored as 256×256 WebP whatever was uploaded, under a random name, and
 * the previous picture is deleted once the change is committed.
 */
final class UpdateProfile
{
    /**
     * @param  array{name?: string, username?: string, email?: string, bio?: string|null, location?: string|null, website?: string|null, locale?: string|null}  $data
     */
    public function handle(User $user, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): User
    {
        $disk = Storage::disk(config('forum.profiles.avatar.disk'));
        $previous = $user->avatar_path;
        $size = max(1, (int) config('forum.profiles.avatar.size'));

        // Encoded before the transaction: it is the slow part and touches no row.
        $stored = $avatar === null ? null : (Image::fromUpload($avatar)
            ->orient()
            ->cover($size, $size)
            ->toWebp()
            ->store('avatars', config('forum.profiles.avatar.disk'))
            ?: throw new RuntimeException('The avatar could not be stored.'));

        try {
            $emailChanged = DB::transaction(function () use ($user, $data, $stored, $removeAvatar): bool {
                $user->fill($data);

                if (is_string($stored)) {
                    $user->avatar_path = $stored;
                } elseif ($removeAvatar) {
                    $user->avatar_path = null;
                }

                if ($user->isDirty('username')) {
                    RetiredUsername::retire($user, (string) $user->getOriginal('username'), $user->username);
                }

                $emailChanged = $user->isDirty('email');

                if ($emailChanged) {
                    $user->email_verified_at = null;
                }

                $user->save();

                return $emailChanged;
            });
        } catch (Throwable $exception) {
            // The row kept the previous picture: the new file belongs to nobody.
            if (is_string($stored)) {
                $disk->delete($stored);
            }

            throw $exception;
        }

        if ($previous !== null && $previous !== $user->avatar_path) {
            $disk->delete($previous);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
