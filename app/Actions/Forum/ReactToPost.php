<?php

declare(strict_types=1);

namespace App\Actions\Forum;

use App\Enums\ReactionType;
use App\Events\ReactionAdded;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Services\Reputation;
use Illuminate\Support\Facades\DB;

/**
 * Adds and removes reactions. The unique index decides whether anything
 * changed, so a double click or a retried request never adds a reaction
 * or its reputation twice, and removing a missing reaction takes nothing.
 */
final class ReactToPost
{
    public function __construct(private readonly Reputation $reputation) {}

    /**
     * @return bool whether the reaction was added (false if it already existed)
     */
    public function add(Post $post, User $user, ReactionType $type): bool
    {
        return DB::transaction(function () use ($post, $user, $type): bool {
            $now = now();

            $added = Reaction::query()->insertOrIgnore([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'type' => $type->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;

            if ($added) {
                $this->reputation->reaction($post, 1);

                ReactionAdded::dispatch($post, $user, $type);
            }

            return $added;
        });
    }

    /**
     * @return bool whether a reaction was removed (false if there was none)
     */
    public function remove(Post $post, User $user, ReactionType $type): bool
    {
        return DB::transaction(function () use ($post, $user, $type): bool {
            $removed = Reaction::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->delete() === 1;

            if ($removed) {
                $this->reputation->reaction($post, -1);
            }

            return $removed;
        });
    }

    /**
     * @return bool whether the reaction is active afterwards
     */
    public function toggle(Post $post, User $user, ReactionType $type): bool
    {
        if ($this->remove($post, $user, $type)) {
            return false;
        }

        $this->add($post, $user, $type);

        return true;
    }
}
