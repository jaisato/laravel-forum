<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Services\Search\ForumSearch;
use Illuminate\Pagination\LengthAwarePaginator;

function search(): ForumSearch
{
    return app(ForumSearch::class);
}

/**
 * @return list<int>
 */
function resultIds(LengthAwarePaginator $results): array
{
    return array_map(fn ($model): int => $model->id, $results->items());
}

it('finds threads by title and opening post', function () {
    $byTitle = forumThread(body: 'Nada que ver');
    $byTitle->forceFill(['title' => 'Configurar Horizon en Docker'])->save();
    $byBody = forumThread(body: 'Mi cola de Horizon no arranca');
    forumThread(body: 'Otra cosa distinta');

    $results = search()->threads('horizon', []);

    expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($results->total())->toBe(2)
        ->and(resultIds($results))->toEqualCanonicalizing([$byTitle->id, $byBody->id]);
});

it('filters threads by category and by tag', function () {
    $laravel = Category::factory()->create();
    $vue = Tag::factory()->create();
    $inCategory = forumThread(category: $laravel, body: 'Pregunta sobre colas');
    $tagged = forumThread(body: 'Pregunta sobre colas', tagIds: [$vue->id]);
    forumThread(body: 'Pregunta sobre colas');

    expect(resultIds(search()->threads('colas', ['category' => $laravel->id])))->toBe([$inCategory->id])
        ->and(resultIds(search()->threads('colas', ['tag' => $vue->id])))->toBe([$tagged->id]);
});

it('never finds hidden, held or deleted threads', function () {
    $public = forumThread(body: 'Secreto compartido');
    $held = forumThread(body: 'Secreto compartido');
    $hidden = forumThread(body: 'Secreto compartido');
    $deleted = forumThread(body: 'Secreto compartido');
    $moderator = User::factory()->moderator()->create();
    app(HidePost::class)->handle($held->firstPost, null);
    app(HidePost::class)->handle($hidden->firstPost, $moderator);
    app(DeleteThread::class)->handle($deleted, $moderator);

    expect(resultIds(search()->threads('secreto', [])))->toBe([$public->id]);
});

it('finds posts and filters them by category and tag', function () {
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $thread = forumThread(category: $category, tagIds: [$tag->id]);
    $match = forumReply($thread, body: 'La migración falla en PostgreSQL');
    forumReply(forumThread(), body: 'La migración falla en SQLite');

    expect(search()->posts('migración', [])->total())->toBe(2)
        ->and(resultIds(search()->posts('migración', ['category' => $category->id])))->toBe([$match->id])
        ->and(resultIds(search()->posts('migración', ['tag' => $tag->id])))->toBe([$match->id]);
});

it('never finds hidden or deleted posts, nor posts of hidden threads', function () {
    $thread = forumThread();
    $public = forumReply($thread, body: 'Palabra clave');
    $hidden = forumReply($thread, body: 'Palabra clave');
    $deleted = forumReply($thread, body: 'Palabra clave');
    $hiddenThread = forumThread();
    forumReply($hiddenThread, body: 'Palabra clave');
    $moderator = User::factory()->moderator()->create();
    app(HidePost::class)->handle($hidden, $moderator);
    app(DeletePost::class)->handle($deleted, $moderator);
    app(HidePost::class)->handle($hiddenThread->firstPost, $moderator);

    expect(resultIds(search()->posts('palabra clave', [])))->toBe([$public->id]);
});

it('eager loads what the results page shows', function () {
    forumReply(forumThread(), body: 'Contenido indexado');
    forumReply(forumThread(), body: 'Contenido indexado');

    $threads = search()->threads('thread', []);
    $posts = search()->posts('indexado', []);

    expect(collect($threads->items())->every(fn (Thread $thread): bool => $thread->relationLoaded('category') && $thread->relationLoaded('author')))->toBeTrue()
        ->and(collect($posts->items())->every(fn ($post): bool => $post->relationLoaded('thread') && $post->relationLoaded('author')))->toBeTrue();
});
