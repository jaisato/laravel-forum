<?php

declare(strict_types=1);

use App\Actions\Forum\CreateThread;
use App\Actions\Forum\ReplyToThread;
use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
|
| Feature tests boot the application against a fresh database for every
| test. Unit tests boot the application too (models and resources reach for
| config and the container) but never touch a connection, so they skip the
| per-test migration cost.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A user playing one of the roles of the permissions matrix (spec §5).
 * "guest" is no user at all.
 */
function forumUser(string $role): ?User
{
    return match ($role) {
        'guest' => null,
        'unverified' => User::factory()->unverified()->create(),
        'banned' => User::factory()->banned()->create(),
        'member' => User::factory()->create(),
        'moderator' => User::factory()->moderator()->create(),
        'banned moderator' => User::factory()->moderator()->banned()->create(),
        'admin' => User::factory()->admin()->create(),
        'banned admin' => User::factory()->admin()->banned()->create(),
    };
}

/**
 * Whether the gate lets the given user (or a guest) perform the ability.
 */
function allows(?User $user, string $ability, mixed $arguments = []): bool
{
    return Gate::forUser($user)->allows($ability, $arguments);
}

/**
 * A thread created the way members create them (CreateThread), so its
 * denormalised counters are right.
 *
 * @param  list<int>  $tagIds
 */
function forumThread(?User $author = null, ?Category $category = null, string $body = 'The opening post of this thread.', array $tagIds = []): Thread
{
    return app(CreateThread::class)->handle(
        $author ?? User::factory()->create(),
        $category ?? Category::factory()->create(),
        'A thread about testing the forum',
        $body,
        $tagIds,
    );
}

/**
 * A reply written through ReplyToThread.
 */
function forumReply(Thread $thread, ?User $author = null, string $body = 'A reply to the thread.'): Post
{
    return app(ReplyToThread::class)->handle($thread, $author ?? User::factory()->create(), $body);
}

/**
 * An API token for the user, as the plain text withToken() sends; every
 * ability a member may give unless others are named.
 *
 * @param  list<string>  $abilities
 */
function apiToken(User $user, array $abilities = User::TOKEN_ABILITIES): string
{
    return $user->createToken('tests', $abilities)->plainTextToken;
}

/**
 * Swaps the search engine for an index that lags behind the database:
 * whatever is searched, it answers with these models' ids (deleted or
 * hidden ones included, as a real index does until it catches up), and the
 * forum loads them the way Scout loads any hit.
 *
 * @param  list<Model>  $hits
 */
function staleSearchIndex(array $hits): void
{
    app(EngineManager::class)->extend('stale', fn () => new class($hits) extends Engine
    {
        /** @param  list<Model>  $hits */
        public function __construct(private readonly array $hits) {}

        public function update($models): void {}

        public function delete($models): void {}

        /** @return list<int|string> */
        public function search(ScoutBuilder $builder): array
        {
            return $this->idsOf($builder->model);
        }

        /** @return list<int|string> */
        public function paginate(ScoutBuilder $builder, $perPage, $page): array
        {
            return $this->idsOf($builder->model);
        }

        /** @return Collection<int, int|string> */
        public function mapIds($results): Collection
        {
            return collect($results);
        }

        /** @return EloquentCollection<int, Model> */
        public function map(ScoutBuilder $builder, $results, $model): EloquentCollection
        {
            return $model->queryScoutModelsByIds($builder, $results)->get();
        }

        /** @return LazyCollection<int, Model> */
        public function lazyMap(ScoutBuilder $builder, $results, $model): LazyCollection
        {
            return LazyCollection::make($this->map($builder, $results, $model)->all());
        }

        public function getTotalCount($results): int
        {
            return count($results);
        }

        public function flush($model): void {}

        public function createIndex($name, array $options = []): void {}

        public function deleteIndex($name): void {}

        /** @return list<int|string> */
        private function idsOf(Model $model): array
        {
            return array_values(array_map(
                fn (Model $hit): int|string => $hit->getScoutKey(),
                array_filter($this->hits, fn (Model $hit): bool => $hit instanceof $model),
            ));
        }
    });

    config(['scout.driver' => 'stale']);
}
