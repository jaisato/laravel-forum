<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $thread_id
 * @property int $user_id
 * @property int|null $reply_to_id
 * @property string $body
 * @property string $body_html
 * @property PostStatus $status
 * @property CarbonImmutable|null $edited_at
 * @property int|null $edited_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['thread_id', 'user_id', 'reply_to_id', 'body', 'body_html'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The database defaults, so a model that was just saved behaves like a
     * reloaded one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PostStatus::Published->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id')->withTrashed();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Posts a viewer may see: everyone sees published ones, authors also see
     * their own posts awaiting review, staff see every status.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->isStaff()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($viewer): void {
            $query->where('status', PostStatus::Published);

            if ($viewer !== null) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('status', PostStatus::PendingReview)
                    ->where('user_id', $viewer->id));
            }
        });
    }

    /**
     * The scopeVisibleTo() rule for a single loaded post; deleted posts are
     * visible to staff only. Whether the thread itself is visible is checked
     * separately (see PostPolicy::view()).
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer?->isStaff()) {
            return true;
        }

        if ($this->trashed()) {
            return false;
        }

        return $this->status === PostStatus::Published
            || ($this->status === PostStatus::PendingReview && $viewer !== null && $this->isAuthoredBy($viewer));
    }

    public function isAuthoredBy(User $user): bool
    {
        return (int) $this->user_id === $user->id;
    }

    /**
     * Whether this post opens its thread (its body) rather than replying.
     */
    public function isFirst(): bool
    {
        $firstPostId = $this->relationLoaded('thread')
            ? $this->thread?->first_post_id
            : Thread::withTrashed()->whereKey($this->thread_id)->value('first_post_id');

        return $firstPostId !== null && (int) $firstPostId === $this->id;
    }
}
