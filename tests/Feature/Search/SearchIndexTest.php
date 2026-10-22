<?php

declare(strict_types=1);

use App\Actions\Forum\UpdateThread;
use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\MoveThread;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\Search\ForumSearch;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;

/**
 * A search engine that records what Scout sends to it.
 */
class SpySearchEngine extends NullEngine
{
    /** @var list<array{string, string, int}> */
    public array $calls = [];

    public ?Builder $lastSearch = null;

    public function update($models): void
    {
        foreach ($models as $model) {
            $this->calls[] = ['update', $model::class, $model->getKey()];
        }
    }

    public function delete($models): void
    {
        foreach ($models as $model) {
            $this->calls[] = ['delete', $model::class, $model->getKey()];
        }
    }

    public function paginate(Builder $builder, $perPage, $page): array
    {
        $this->lastSearch = $builder;

        return [];
    }

    /**
     * @return list<array{string, string, int}>
     */
    public function callsFor(string $operation, string $model): array
    {
        return array_values(array_filter($this->calls, fn (array $call): bool => $call[0] === $operation && $call[1] === $model));
    }
}

beforeEach(function () {
    // Manager::extend() binds the callback to the manager, so the engine is
    // captured from a local variable rather than from $this.
    $engine = $this->engine = new SpySearchEngine;
    app(EngineManager::class)->extend('spy', fn () => $engine);
    config(['scout.driver' => 'spy']);
});

it('indexes public threads and posts with what the filters need', function () {
    $tag = Tag::factory()->create();
    $thread = forumThread(body: 'Hola **mundo**', tagIds: [$tag->id]);
    $reply = forumReply($thread, body: 'Respuesta');

    expect($thread->fresh()->toSearchableArray())->toMatchArray([
        'id' => $thread->id,
        'title' => $thread->title,
        'body' => 'Hola mundo',
        'category_id' => $thread->category_id,
        'tag_ids' => [$tag->id],
        'is_solved' => false,
    ])
        ->and($reply->fresh()->toSearchableArray())->toMatchArray([
            'id' => $reply->id,
            'body' => 'Respuesta',
            'thread_id' => $thread->id,
            'thread_title' => $thread->title,
            'category_id' => $thread->category_id,
            'tag_ids' => [$tag->id],
        ])
        ->and($this->engine->callsFor('update', Thread::class))->not->toBeEmpty()
        ->and($this->engine->callsFor('update', Post::class))->not->toBeEmpty();
});

it('only keeps public content searchable', function () {
    $thread = forumThread();
    $reply = forumReply($thread);

    app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

    expect($reply->fresh()->shouldBeSearchable())->toBeFalse()
        ->and($thread->fresh()->shouldBeSearchable())->toBeTrue()
        ->and($this->engine->callsFor('delete', Post::class))->toContain(['delete', Post::class, $reply->id]);
});

it('takes the posts of a hidden thread out of the index', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $this->engine->calls = [];

    app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

    expect($this->engine->callsFor('delete', Post::class))->toContain(['delete', Post::class, $reply->id])
        ->and($this->engine->callsFor('delete', Thread::class))->toContain(['delete', Thread::class, $thread->id]);
});

it('reindexes the posts of a thread that moved or changed its tags', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $moderator = User::factory()->moderator()->create();
    $this->engine->calls = [];

    app(MoveThread::class)->handle($thread, Category::factory()->create(), $moderator);

    expect($this->engine->callsFor('update', Post::class))->toContain(['update', Post::class, $reply->id]);

    $this->engine->calls = [];
    app(UpdateThread::class)->handle($thread->fresh(), $moderator, $thread->title, [Tag::factory()->create()->id]);

    expect($this->engine->callsFor('update', Post::class))->toContain(['update', Post::class, $reply->id]);
});

it('sends category and tag filters to a real search engine', function () {
    app(ForumSearch::class)->threads('colas', ['category' => 7, 'tag' => 9]);

    expect($this->engine->lastSearch->wheres)->toContain(['field' => 'category_id', 'operator' => '=', 'value' => 7])
        ->and($this->engine->lastSearch->whereIns)->toBe(['tag_ids' => [9]]);
});
