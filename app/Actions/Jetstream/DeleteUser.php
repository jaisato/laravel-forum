<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     *
     * posts.user_id and comments.user_id are declared restrictOnDelete, so a
     * bare $user->delete() raised a foreign-key violation - a 500 on the
     * "Delete Account" form - for anybody who had ever written a post or a
     * comment. The user's content goes first: their comments, then their posts
     * (whose remaining comments follow through comments.post_id's cascade).
     *
     * It all runs in one transaction so a failure part-way cannot leave an
     * account whose content is gone but which still exists.
     */
    public function delete(User $user): void
    {
        // First, as Jetstream orders it: this saves the model (it nulls
        // profile_photo_path), which on an already deleted user would insert it
        // back.
        $user->deleteProfilePhoto();

        DB::transaction(function () use ($user) {
            $user->comments()->delete();
            $user->posts()->delete();
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
