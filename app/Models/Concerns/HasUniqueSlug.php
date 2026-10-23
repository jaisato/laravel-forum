<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Gives a model a unique, URL-safe slug the moment it is created.
 *
 * The slug never changes afterwards, so links shared before a title edit keep
 * working. Uniqueness is checked against soft-deleted rows too: a restored
 * record must not collide with one created while it was deleted. When two
 * requests pick the same free slug at once, the loser takes the next one.
 *
 * @mixin Model
 */
trait HasUniqueSlug
{
    /**
     * The shortest slug column (categories and tags), suffix included.
     */
    private const int SLUG_MAX_LENGTH = 80;

    private const int SLUG_ATTEMPTS = 3;

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
        $base = Str::slug($source);

        if ($base === '') {
            $base = Str::snake(class_basename($this));
        }

        $slug = $this->fitSlug($base, '');
        $suffix = 2;

        while ($this->newQueryWithoutScopes()->where('slug', $slug)->exists()) {
            $slug = $this->fitSlug($base, "-{$suffix}");
            $suffix++;
        }

        return $slug;
    }

    /**
     * The base cut short enough for the suffix to fit in the column, without
     * a dash left dangling at the cut.
     */
    private function fitSlug(string $base, string $suffix): string
    {
        return rtrim(Str::substr($base, 0, self::SLUG_MAX_LENGTH - strlen($suffix)), '-').$suffix;
    }

    /**
     * Retries the insert with the next free slug when another request took
     * this one between the check and the insert. Each attempt runs in its own
     * savepoint: on PostgreSQL a failed statement would otherwise abort the
     * whole surrounding transaction.
     *
     * @param  Builder<static>  $query
     */
    protected function performInsert(Builder $query): bool
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->getConnection()->transaction(fn (): bool => parent::performInsert($query));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::SLUG_ATTEMPTS || ! str_contains($exception->getMessage(), 'slug')) {
                    throw $exception;
                }

                $this->setAttribute('slug', $this->uniqueSlug($this->slugSource()));
            }
        }
    }
}
