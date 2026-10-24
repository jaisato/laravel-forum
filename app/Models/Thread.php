<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadStatus;
use App\Models\Concerns\HasModerationStatus;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\Markdown\MarkdownRenderer;
use Carbon\CarbonImmutable;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
 * @property int|null $held_solution_post_id
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
    use HasFactory, HasModerationStatus, HasUniqueSlug, Searchable, SoftDeletes;

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
     * Characters of the opening post read for a listing's excerpt: enough
     * Markdown for a short summary without loading whole posts.
     */
    private const int EXCERPT_SOURCE_LENGTH = 600;

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

    protected function statusEnum(): string
    {
        return ThreadStatus::class;
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
     * Adds excerpt_source, the start of the opening post, from which
     * ThreadResource makes the excerpt of a listing.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithExcerptSource(Builder $query): Builder
    {
        return $query->addSelect(['excerpt_source' => Post::query()
            ->selectRaw('substr(body, 1, '.self::EXCERPT_SOURCE_LENGTH.')')
            ->whereColumn('posts.id', $query->qualifyColumn('first_post_id'))]);
    }

    /**
     * Whether the user may reply (ThreadPolicy::reply, and ReplyToThread
     * again under lock): staff always may; members who may take part while
     * the thread is published, not deleted, and neither it nor its category
     * is locked.
     */
    public function acceptsRepliesFrom(User $user): bool
    {
        if ($user->canModerate()) {
            return true;
        }

        return $user->canParticipate()
            && $this->status === ThreadStatus::Published
            && ! $this->trashed()
            && ! $this->is_locked
            && ! $this->category->is_locked;
    }

    /**
     * Whether the user may edit the thread or choose its solution
     * (ThreadPolicy, and the actions again under lock): staff always may;
     * its author while they may change it.
     */
    public function mayBeChangedBy(User $user): bool
    {
        return $user->canModerate() || $this->authorMayChange($user);
    }

    /**
     * Whether the user may delete the thread (ThreadPolicy::delete, and
     * DeleteThread again under lock): staff always may; its author only
     * while they may change it and nobody has replied.
     */
    public function mayBeDeletedBy(User $user): bool
    {
        return $user->canModerate()
            || ($this->authorMayChange($user) && $this->replies_count === 0);
    }

    /**
     * Whether its author may still edit the thread, choose its solution or
     * delete it: they may take part and the thread is not hidden, deleted
     * or locked.
     */
    public function authorMayChange(User $user): bool
    {
        return $user->canParticipate()
            && $this->isAuthoredBy($user)
            && $this->status !== ThreadStatus::Hidden
            && ! $this->trashed()
            && ! $this->is_locked;
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
        return $models instanceof EloquentCollection && $models->count() > 1 ? $models->loadMissing(['firstPost', 'tags']) : $models;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['firstPost', 'tags']);
    }

    /**
     * AI summaries are for public threads with enough replies to sum up
     * (spec §4.6): everyone reads them.
     */
    public function canHaveSummary(): bool
    {
        return ! $this->trashed()
            && $this->status === ThreadStatus::Published
            && $this->replies_count >= (int) config('forum.ai.summaries.min_replies');
    }

    /**
     * Forgets the AI summaries of these threads. A summary may restate what
     * a post said before it was edited, hidden, held or deleted, or name an
     * account that was erased since; the next one is written when someone
     * asks for it.
     *
     * @param  Builder<self>  $threads
     */
    public static function forgetSummaries(Builder $threads): void
    {
        $threads->withoutGlobalScopes()->whereNotNull('ai_summary')->toBase()->update([
            'ai_summary' => null,
            'ai_summary_generated_at' => null,
            'ai_summary_replies_count' => null,
        ]);
    }

    /**
     * Whether the stored summary should be written again: there is none,
     * enough replies arrived since, or enough time went by.
     */
    public function summaryIsStale(): bool
    {
        if ($this->ai_summary === null || $this->ai_summary_generated_at === null) {
            return true;
        }

        return $this->replies_count - (int) $this->ai_summary_replies_count >= (int) config('forum.ai.summaries.regenerate_after_replies')
            || $this->ai_summary_generated_at->addHours((int) config('forum.ai.summaries.regenerate_after_hours'))->isPast();
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
