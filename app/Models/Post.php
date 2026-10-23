<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\ReactionType;
use App\Support\Markdown\MarkdownRenderer;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

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
    use HasFactory, Searchable, SoftDeletes;

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
     * their own posts awaiting review, staff see every status (not while
     * banned: a ban takes the staff powers away).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer?->canModerate()) {
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
     * Adds, for every reaction type, how many reactions the post has
     * ({type}_reactions_count) and whether the viewer gave one
     * ({type}_reacted), with one subquery each instead of loading reactions.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithReactionSummary(Builder $query, ?User $viewer): Builder
    {
        foreach (ReactionType::cases() as $type) {
            $query->withCount(["reactions as {$type->value}_reactions_count" => fn (Builder $reactions) => $reactions->where('type', $type)]);

            if ($viewer !== null) {
                $query->withExists(["reactions as {$type->value}_reacted" => fn (Builder $reactions) => $reactions
                    ->where('type', $type)
                    ->where('user_id', $viewer->id)]);
            }
        }

        return $query;
    }

    /**
     * The scopeVisibleTo() rule for a single loaded post; deleted posts are
     * visible to staff only. Whether the thread itself is visible is checked
     * separately (see PostPolicy::view()).
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($viewer?->canModerate()) {
            return true;
        }

        if ($this->trashed()) {
            return false;
        }

        return $this->status === PostStatus::Published
            || ($this->status === PostStatus::PendingReview && $viewer !== null && $this->isAuthoredBy($viewer));
    }

    /**
     * Visible to everyone: published and not deleted, in a thread that is
     * published and not deleted either.
     */
    public function isPublic(): bool
    {
        return $this->isPublicIn($this->thread);
    }

    /**
     * Link to the post inside its thread; the thread page uses ?post= to
     * open the page that holds it.
     */
    public function url(): string
    {
        $thread = $this->thread ?? Thread::withTrashed()->findOrFail($this->thread_id);

        return $thread->url().'?post='.$this->id.'#post-'.$this->id;
    }

    /**
     * Only public posts are searchable; posts of a hidden or deleted thread
     * are not public either.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublicIn($this->threadForSearch());
    }

    /**
     * Thread data is copied in so search engines can filter by category and
     * tag without a join.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $thread = $this->threadForSearch();

        return [
            'id' => $this->id,
            'body' => app(MarkdownRenderer::class)->excerpt($this->body, 10000),
            'thread_id' => $this->thread_id,
            'thread_title' => $thread?->title,
            'category_id' => $thread?->category_id,
            'tag_ids' => $thread?->tags->modelKeys() ?? [],
            'created_at' => $this->created_at?->getTimestamp(),
        ];
    }

    /**
     * Batches (imports, re-syncs, database searches) are eager loaded; a
     * single saved model is left as it is (see threadForSearch()).
     *
     * @param  Collection<int, self>  $models
     * @return Collection<int, self>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models instanceof EloquentCollection && $models->count() > 1 ? $models->loadMissing('thread.tags') : $models;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('thread.tags');
    }

    private function isPublicIn(?Thread $thread): bool
    {
        return $this->isVisibleTo(null) && $thread?->isVisibleTo(null) === true;
    }

    /**
     * The thread for indexing. When it is not loaded it is queried without
     * being cached on the post: Scout indexes live instances right after a
     * save, and a relation cached then would go stale for the request.
     */
    private function threadForSearch(): ?Thread
    {
        return $this->relationLoaded('thread') ? $this->thread : $this->thread()->with('tags')->first();
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
