<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadStatus;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\Markdown\MarkdownRenderer;
use Carbon\CarbonImmutable;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

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
 * @property CarbonImmutable|null $last_posted_at
 * @property int $replies_count
 * @property int $views_count
 * @property string|null $ai_summary
 * @property CarbonImmutable|null $ai_summary_generated_at
 * @property int|null $ai_summary_replies_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['category_id', 'user_id', 'title'])]
class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory, HasUniqueSlug, Searchable, SoftDeletes;

    /**
     * The database defaults, so a model that was just saved behaves like a
     * reloaded one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ThreadStatus::Published->value,
        'is_pinned' => false,
        'is_locked' => false,
        'replies_count' => 0,
        'views_count' => 0,
    ];

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
     * @return HasMany<ThreadSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ThreadSubscription::class);
    }

    /**
     * Members following the thread. Erased accounts drop out through the
     * user soft delete scope, so they are never notified.
     *
     * @return BelongsToMany<User, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_subscriptions')->withTimestamps();
    }

    /**
     * @return HasMany<ThreadRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ThreadRead::class);
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

    /**
     * Adds the viewer's read marker to every thread: last_read_post_id and
     * last_read_at, both null for guests and for threads never read. A
     * thread is unread while last_post_id is above last_read_post_id.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithReadState(Builder $query, ?User $viewer): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        $query->withCasts(['last_read_post_id' => 'integer', 'last_read_at' => 'datetime']);

        if ($viewer === null) {
            return $query->selectRaw('null as last_read_post_id, null as last_read_at');
        }

        $marker = fn (string $column): Builder => ThreadRead::query()
            ->select($column)
            ->whereColumn('thread_reads.thread_id', $query->qualifyColumn('id'))
            ->where('thread_reads.user_id', $viewer->id)
            ->limit(1);

        return $query->addSelect([
            'last_read_post_id' => $marker('last_read_post_id'),
            'last_read_at' => $marker('last_read_at'),
        ]);
    }

    /**
     * The scopeVisibleTo() rule for a single loaded thread; deleted threads
     * are visible to staff only.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer?->isStaff()) {
            return true;
        }

        if ($this->trashed()) {
            return false;
        }

        return $this->status === ThreadStatus::Published
            || ($this->status === ThreadStatus::PendingReview && $viewer !== null && $this->isAuthoredBy($viewer));
    }

    public function isAuthoredBy(User $user): bool
    {
        return (int) $this->user_id === $user->id;
    }

    public function url(): string
    {
        return url('/t/'.$this->slug);
    }

    /**
     * Only public threads are searchable: Scout drops the others from the
     * index as soon as they stop being public.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isVisibleTo(null);
    }

    /**
     * Relations that are not loaded are queried without being cached on the
     * model: Scout indexes live instances right after they are saved, and a
     * relation cached then (say, before the opening post exists) would be
     * stale for the rest of the request.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $firstPost = $this->relationLoaded('firstPost') ? $this->firstPost : $this->firstPost()->first();
        $tags = $this->relationLoaded('tags') ? $this->tags : $this->tags()->get();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $firstPost === null ? '' : app(MarkdownRenderer::class)->excerpt($firstPost->body, 5000),
            'category_id' => $this->category_id,
            'tag_ids' => $tags->modelKeys(),
            'is_solved' => $this->isSolved(),
            'last_posted_at' => $this->last_posted_at?->getTimestamp(),
            'created_at' => $this->created_at?->getTimestamp(),
        ];
    }

    /**
     * Batches (imports, re-syncs, database searches) are eager loaded; a
     * single saved model is left as it is (see toSearchableArray()).
     *
     * @param  Collection<int, self>  $models
     * @return Collection<int, self>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->count() > 1 ? $models->loadMissing(['firstPost', 'tags']) : $models;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['firstPost', 'tags']);
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
