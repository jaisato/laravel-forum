<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A discussion. Its body is the first post, so editing, reactions and
 * reports work the same way for the opening message and for replies.
 *
 * @property int $id
 * @property int $category_id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property ThreadStatus $status
 * @property bool $is_pinned
 * @property bool $is_locked
 * @property int|null $first_post_id
 * @property int|null $last_post_id
 * @property int|null $solution_post_id
 * @property Carbon|null $last_posted_at
 * @property int $replies_count
 * @property int $views_count
 * @property string|null $ai_summary
 * @property Carbon|null $ai_summary_generated_at
 * @property int|null $ai_summary_replies_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['category_id', 'user_id', 'title'])]
class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory, HasUniqueSlug, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ThreadStatus::class,
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_posted_at' => 'datetime',
            'replies_count' => 'integer',
            'views_count' => 'integer',
            'ai_summary_generated_at' => 'datetime',
            'ai_summary_replies_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function firstPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'first_post_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function lastPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'last_post_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function solution(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'solution_post_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Threads a viewer may see: everyone sees published ones, authors also
     * see their own threads awaiting review, staff see every status.
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
            $query->where('status', ThreadStatus::Published);

            if ($viewer !== null) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('status', ThreadStatus::PendingReview)
                    ->where('user_id', $viewer->id));
            }
        });
    }

    public function isSolved(): bool
    {
        return $this->solution_post_id !== null;
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
