<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ThreadStatus;
use App\Models\Concerns\HasUniqueSlug;
use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $color
 * @property string|null $icon
 * @property int $position
 * @property bool $is_locked
 * @property int $threads_count
 * @property int $posts_count
 * @property int|null $latest_thread_id withLatestThread()
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'description', 'color', 'icon', 'position', 'is_locked'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * The database defaults, so a model that was just saved behaves like a
     * reloaded one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'color' => '#6366f1',
        'position' => 0,
        'is_locked' => false,
        'threads_count' => 0,
        'posts_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_locked' => 'boolean',
            'threads_count' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<Thread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    /**
     * The public thread with the most recent activity, for the category
     * cards: loaded through withLatestThread(), which finds its id.
     *
     * @return BelongsTo<Thread, $this>
     */
    public function latestThread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'latest_thread_id');
    }

    /**
     * Whether the user may start threads here (ThreadPolicy::create, and
     * CreateThread again under lock): staff always may; members who may take
     * part, unless the category is locked.
     */
    public function acceptsThreadsFrom(User $user): bool
    {
        return $user->canModerate() || ($user->canParticipate() && ! $this->is_locked);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * Loads each category's public thread with the most recent activity
     * (latestThread), with its last post and that post's author. Its id is
     * read one category at a time, from the newest end of the index on
     * category, status and activity, so the cost does not grow with the
     * number of threads, as an aggregate over all of them would.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithLatestThread(Builder $query): Builder
    {
        return $query
            ->addSelect(['latest_thread_id' => Thread::query()
                ->select('id')
                ->whereColumn('threads.category_id', 'categories.id')
                ->where('status', ThreadStatus::Published)
                ->whereNotNull('last_posted_at')
                ->orderByDesc('last_posted_at')
                ->orderByDesc('id')
                ->limit(1),
            ])
            ->with('latestThread.lastPost.author');
    }

    protected function slugSource(): string
    {
        return $this->name;
    }
}
