<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property int $threads_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'color'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * The database defaults, so a model that was just saved behaves like a
     * reloaded one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'threads_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threads_count' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Thread, $this>
     */
    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(Thread::class);
    }

    /**
     * The given ids that belong to existing tags, once each: tag ids come
     * from forms and API clients.
     *
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    public static function existingIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(intval(...), $ids))));

        return $ids === []
            ? []
            : static::query()->whereKey($ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function slugSource(): string
    {
        return $this->name;
    }
}
