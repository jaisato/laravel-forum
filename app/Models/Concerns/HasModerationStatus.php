<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see a thread or a post, by its moderation status (spec §5):
 * everyone sees what is published, authors also see their own awaiting
 * review, and staff see every status and the deleted ones (not while
 * banned: a ban takes the staff powers away). The same rule for both
 * models, in queries (visibleTo) and on a loaded record (isVisibleTo).
 *
 * @mixin Model
 *
 * @method bool trashed()
 */
trait HasModerationStatus
{
    /**
     * The status enum of the model; ThreadStatus and PostStatus share their
     * cases.
     *
     * @return class-string<ThreadStatus>|class-string<PostStatus>
     */
    abstract protected function statusEnum(): string;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->canModerate()) {
            return $query;
        }

        $status = $this->statusEnum();

        return $query->where(function (Builder $query) use ($viewer, $status): void {
            $query->where('status', $status::Published);

            if ($viewer !== null) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('status', $status::PendingReview)
                    ->where('user_id', $viewer->id));
            }
        });
    }

    /**
     * The visibleTo() rule for a single loaded record; deleted records are
     * visible to staff only.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer?->canModerate()) {
            return true;
        }

        if ($this->trashed()) {
            return false;
        }

        $status = $this->statusEnum();

        return $this->status === $status::Published
            || ($this->status === $status::PendingReview && $viewer !== null && $this->isAuthoredBy($viewer));
    }

    public function isAuthoredBy(User $user): bool
    {
        return (int) $this->user_id === $user->id;
    }
}
