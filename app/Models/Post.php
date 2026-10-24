<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\ReactionType;
use App\Enums\ThreadStatus;
use App\Models\Concerns\HasModerationStatus;
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
    use HasFactory, HasModerationStatus, Searchable, SoftDeletes;

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

    protected function statusEnum(): string
    {
        return PostStatus::class;
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
     * Whether the viewer may see the post where it is (PostPolicy::view()):
     * the post and its thread, by the HasModerationStatus rule. A deleted
     * thread takes its posts along; staff see them all.
     */
    public function isVisibleInThreadTo(?User $viewer): bool
    {
        return $viewer?->canModerate() === true
            || ($this->isVisibleTo($viewer) && $this->thread?->isVisibleTo($viewer) === true);
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

    /**
     * Whether the user may report the post (PostPolicy::report, and
     * ReportPost again under lock): members who may take part, on a post of
     * someone else that everybody can see, in a published thread.
     */
    public function mayBeReportedBy(User $user): bool
    {
        return $user->canParticipate()
            && ! $this->isAuthoredBy($user)
            && $this->status === PostStatus::Published
            && ! $this->trashed()
            && $this->thread?->status === ThreadStatus::Published
            // The relation may hold a deleted thread (the thread page loads
            // them for staff); the routes would not find it.
            && ! $this->thread->trashed();
    }

    /**
     * Whether the user may react to the post (PostPolicy::react, and
     * ReactToPost again under lock): as for reports, while the thread is not
     * locked.
     */
    public function mayBeReactedToBy(User $user): bool
    {
        return $this->mayBeReportedBy($user) && $this->thread?->is_locked === false;
    }

    /**
     * Whether the user may edit the post, or delete it when it is a reply
     * (PostPolicy, and the actions again under lock): staff always may;
     * its author while they may change it.
     */
    public function mayBeChangedBy(User $user): bool
    {
        return $user->canModerate() || $this->authorMayChange($user);
    }

    /**
     * Whether its author may still change the post: they may take part, the
     * post is neither hidden nor deleted, and its thread is visible to them
     * and not locked.
     */
    public function authorMayChange(User $user): bool
    {
        return $user->canParticipate()
            && $this->isAuthoredBy($user)
            && $this->status !== PostStatus::Hidden
            && ! $this->trashed()
            && $this->thread?->isVisibleTo($user) === true
            && ! $this->thread->is_locked;
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
