<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;

/**
 * Swaps the search engine for one that finds nothing and records what it
 * was asked for.
 *
 * @return ArrayObject<int, string>
 */
function recordSearches(): ArrayObject
{
    $searches = new ArrayObject;

    app(EngineManager::class)->extend('recording', fn () => new class($searches) extends NullEngine
    {
        /** @param  ArrayObject<int, string>  $searches */
        public function __construct(private ArrayObject $searches) {}

        public function search(Builder $builder)
        {
            $this->searches[] = $builder->query;

            return parent::search($builder);
        }

        public function paginate(Builder $builder, $perPage, $page)
        {
            $this->searches[] = $builder->query;

            return parent::paginate($builder, $perPage, $page);
        }
    });

    config(['scout.driver' => 'recording']);

    return $searches;
}

it('does not search before there is something to look for', function () {
    $searches = recordSearches();

    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('search/Index')
            ->where('filters', ['q' => '', 'type' => 'threads', 'category' => null, 'tag' => null])
            ->where('threads', null)
            ->where('posts', null));

    $this->get('/search?q=%20%20&type=posts')->assertInertia(fn (Assert $page) => $page
        ->where('filters.q', '')
        ->where('posts', null));

    expect($searches)->toHaveCount(0);

    $this->get('/search?q=colas');

    expect($searches->getArrayCopy())->toBe(['colas']);
});

it('finds threads, narrowed to a category or a tag', function () {
    $laravel = Category::factory()->create(['slug' => 'laravel']);
    $horizon = Tag::factory()->create(['slug' => 'horizon']);
    $inCategory = forumThread(category: $laravel, body: 'Mis colas no arrancan');
    $tagged = forumThread(body: 'Mis colas no arrancan', tagIds: [$horizon->id]);
    forumThread(body: 'Mis colas no arrancan');
    forumThread(body: 'Otra cosa distinta');

    $this->get('/search?q=colas')->assertInertia(fn (Assert $page) => $page
        ->has('threads.data', 3)
        ->where('threads.data.0.excerpt', 'Mis colas no arrancan')
        ->where('posts', null));

    $this->get('/search?q=colas&category=laravel')->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['q' => 'colas', 'type' => 'threads', 'category' => 'laravel', 'tag' => null])
        ->has('threads.data', 1)
        ->where('threads.data.0.id', $inCategory->id));

    $this->get('/search?q=colas&tag=horizon')->assertInertia(fn (Assert $page) => $page
        ->where('filters.tag', 'horizon')
        ->has('threads.data', 1)
        ->where('threads.data.0.id', $tagged->id));
});

it('finds posts', function () {
    $thread = forumThread();
    $reply = forumReply($thread, body: 'La **migración** falla en PostgreSQL');
    forumReply($thread, body: 'Otra cosa distinta');

    $this->get('/search?q=migración&type=posts')->assertInertia(fn (Assert $page) => $page
        ->where('filters.type', 'posts')
        ->where('threads', null)
        ->has('posts.data', 1)
        ->where('posts.data.0.id', $reply->id)
        ->where('posts.data.0.excerpt', 'La migración falla en PostgreSQL')
        ->where('posts.data.0.author.username', $reply->author->username)
        ->where('posts.data.0.thread', [
            'id' => $thread->id,
            'title' => $thread->title,
            'slug' => $thread->slug,
        ]));
});

it('ignores what it does not understand', function () {
    forumThread(body: 'Mis colas no arrancan');

    $this->get('/search?q=colas&type=users&category=nada&tag=nada')->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['q' => 'colas', 'type' => 'threads', 'category' => null, 'tag' => null])
        ->has('threads.data', 1));
});

it('searches for no more than a few words', function () {
    $searches = recordSearches();

    $this->get('/search?q='.str_repeat('ñ', 500))->assertInertia(fn (Assert $page) => $page
        ->where('filters.q', str_repeat('ñ', 200)));

    expect(mb_strlen($searches[0]))->toBe(200);
});

it('keeps the search in the page links', function () {
    config(['forum.pagination.search' => 1]);
    forumThread(body: 'Mis colas no arrancan');
    forumThread(body: 'Mis colas no arrancan');

    $this->get('/search?q=colas')->assertInertia(fn (Assert $page) => $page
        ->where('threads.links.next', url('/search?q=colas&page=2')));

    forumReply(forumThread(), body: 'Más colas');
    forumReply(forumThread(), body: 'Más colas');

    $this->get('/search?q=colas&type=posts')->assertInertia(fn (Assert $page) => $page
        ->where('posts.links.next', url('/search?q=colas&type=posts&page=2')));
});

it('offers the categories and the tags to narrow the search', function () {
    Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    Tag::factory()->create(['name' => 'eloquent', 'slug' => 'eloquent']);

    $this->get('/search')->assertInertia(fn (Assert $page) => $page
        ->where('categories', [['name' => 'Laravel', 'slug' => 'laravel']])
        ->where('tags', [['name' => 'eloquent', 'slug' => 'eloquent']]));
});

it('never finds what the public may not see, whoever searches', function (string $role) {
    $moderator = User::factory()->moderator()->create();
    $public = forumThread(body: 'Palabra secreta');
    $held = forumThread(body: 'Palabra secreta');
    $hidden = forumThread(body: 'Palabra secreta');
    $deleted = forumThread(body: 'Palabra secreta');
    $publicReply = forumReply($public, body: 'Respuesta secreta');
    $hiddenReply = forumReply($public, body: 'Respuesta secreta');
    forumReply($hidden, body: 'Respuesta secreta');
    app(HidePost::class)->handle($held->firstPost, null);
    app(HidePost::class)->handle($hidden->firstPost, $moderator);
    app(HidePost::class)->handle($hiddenReply, $moderator);
    app(DeleteThread::class)->handle($deleted, $moderator);

    $viewer = forumUser($role);

    if ($viewer !== null) {
        $this->actingAs($viewer);
    }

    $this->get('/search?q=secreta')->assertInertia(fn (Assert $page) => $page
        ->has('threads.data', 1)
        ->where('threads.data.0.id', $public->id));

    $this->get('/search?q=respuesta&type=posts')->assertInertia(fn (Assert $page) => $page
        ->has('posts.data', 1)
        ->where('posts.data.0.id', $publicReply->id));
})->with(['guest', 'member', 'moderator']);

it('slows down whoever searches too fast', function () {
    $searches = recordSearches();

    foreach (range(1, 60) as $i) {
        $this->get("/search?q=colas{$i}")->assertOk();
    }

    $this->get('/search?q=colas')->assertTooManyRequests();

    expect($searches)->toHaveCount(60);
});
