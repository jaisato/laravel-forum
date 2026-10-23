<?php

declare(strict_types=1);

use App\Actions\Forum\UpdatePost;
use App\Actions\Forum\UpdateThread;
use App\Actions\Moderation\HidePost;
use App\Jobs\Ai\GenerateThreadEmbedding;
use App\Models\Thread;
use App\Models\ThreadEmbedding;
use App\Models\User;
use App\Services\Ai\SimilarThreadFinder;
use App\Services\Ai\VectorSimilarThreadFinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/*
 * pgvector lives in PostgreSQL: these run on the PostgreSQL suite only.
 */

$needsPostgres = fn (): bool => DB::connection()->getDriverName() !== 'pgsql';

/**
 * A unit vector pointing at a topic: texts about the same topic get nearly
 * the same vector, texts about others point elsewhere.
 *
 * @return list<float>
 */
function topicVector(string $text): array
{
    $phase = match (true) {
        str_contains($text, 'Horizon') => 0.3,
        str_contains($text, 'colas') => 0.0,
        str_contains($text, 'migraciones') => 2.5,
        default => 5.0,
    };

    $values = array_map(fn (int $i): float => sin($i + $phase), range(1, (int) config('forum.ai.similar.dimensions')));
    $norm = sqrt(array_sum(array_map(fn (float $value): float => $value ** 2, $values)));

    return array_map(fn (float $value): float => $value / $norm, $values);
}

function enableVectorSearch(): void
{
    config([
        'forum.ai.enabled' => true,
        'forum.ai.similar.enabled' => true,
        'forum.ai.moderation.enabled' => false,
    ]);
}

/**
 * Embeds the threads the way the queue does.
 */
function embed(Thread ...$threads): void
{
    foreach ($threads as $thread) {
        app()->call([new GenerateThreadEmbedding($thread), 'handle']);
    }
}

it('embeds the title and the start of the opening post, and again only when they change', function () {
    $calls = 0;
    Embeddings::fake(function (EmbeddingsPrompt $prompt) use (&$calls): array {
        $calls++;

        return [topicVector($prompt->inputs[0])];
    });
    $thread = forumThread(body: 'Mis colas no arrancan');
    enableVectorSearch();

    embed($thread);

    expect(ThreadEmbedding::query()->find($thread->id))
        ->not->toBeNull()
        ->model->toBe('text-embedding-3-small');
    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('A thread about testing the forum')
        && $prompt->contains('Mis colas no arrancan'));

    embed($thread);
    expect($calls)->toBe(1);

    app(UpdateThread::class)->handle($thread, $thread->author, 'Las colas de Horizon no arrancan', null);
    embed($thread->fresh());
    expect($calls)->toBe(2);

    app()->call([new GenerateThreadEmbedding($thread->fresh(), force: true), 'handle']);
    expect($calls)->toBe(3);
})->skip($needsPostgres, 'pgvector lives in PostgreSQL.');

it('finds the nearest public threads, never the thread itself nor hidden ones', function () {
    Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => [topicVector($prompt->inputs[0])]);
    $queues = forumThread(body: 'Mis colas no arrancan');
    $horizon = forumThread(body: 'Horizon no procesa nada');
    $hidden = forumThread(body: 'Más colas que no arrancan');
    $migrations = forumThread(body: 'Las migraciones fallan');
    app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());
    enableVectorSearch();
    embed($queues, $horizon, $hidden, $migrations);

    $finder = app(SimilarThreadFinder::class);

    expect($finder)->toBeInstanceOf(VectorSimilarThreadFinder::class)
        ->and($finder->similarTo($queues)->modelKeys())->toBe([$horizon->id])
        ->and($finder->forText('Problemas con las colas')->modelKeys())->toBe([$queues->id, $horizon->id]);
})->skip($needsPostgres, 'pgvector lives in PostgreSQL.');

it('falls back to keyword search when the embeddings fail, and logs it', function () {
    $thread = forumThread(body: 'Mis colas no arrancan');
    enableVectorSearch();
    Embeddings::fake(fn () => throw new RuntimeException('Embeddings are down'));
    Log::spy();

    expect(app(SimilarThreadFinder::class)->forText('A thread about testing')->modelKeys())->toBe([$thread->id]);

    Log::shouldHaveReceived('warning')->once();
})->skip($needsPostgres, 'pgvector lives in PostgreSQL.');

it('queues the embedding when a thread starts or its title or opening post changes', function () {
    enableVectorSearch();
    Queue::fake();

    $thread = forumThread();
    $reply = forumReply($thread);
    app(UpdateThread::class)->handle($thread, $thread->author, 'Un título distinto para el hilo', null);
    app(UpdatePost::class)->handle($thread->firstPost, $thread->author, 'Un primer mensaje corregido.');
    app(UpdatePost::class)->handle($reply, $reply->author, 'Una respuesta corregida.');

    Queue::assertPushed(GenerateThreadEmbedding::class, 3);
})->skip($needsPostgres, 'pgvector lives in PostgreSQL.');
