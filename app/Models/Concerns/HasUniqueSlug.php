<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a unique, URL-safe slug the moment it is created.
 *
 * The slug never changes afterwards, so links shared before a title edit keep
 * working. Uniqueness is checked against soft-deleted rows too: a restored
 * record must not collide with one created while it was deleted.
 *
 * @mixin Model
 */
trait HasUniqueSlug
{
    private const int SLUG_MAX_LENGTH = 80;

    /**
     * The text the slug is derived from (a title or a name).
     */
    abstract protected function slugSource(): string;

    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            if (blank($model->getAttribute('slug'))) {
                $model->setAttribute('slug', $model->uniqueSlug($model->slugSource()));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function uniqueSlug(string $source): string
    {
        $base = Str::of($source)
            ->slug()
            ->limit(self::SLUG_MAX_LENGTH, '')
            ->trim('-')
            ->toString();

        if ($base === '') {
            $base = Str::snake(class_basename($this));
        }

        $slug = $base;
        $suffix = 2;

        while ($this->newQueryWithoutScopes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
