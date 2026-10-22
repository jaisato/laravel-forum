<?php

declare(strict_types=1);

use App\Models\Thread;
use App\Models\ThreadEmbedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A deterministic unit-length vector with the configured dimensions.
 *
 * @return list<float>
 */
function embeddingFixture(float $phase): array
{
    $values = array_map(fn (int $i): float => sin($i + $phase), range(1, config('forum.ai.similar.dimensions')));
    $norm = sqrt(array_sum(array_map(fn (float $value): float => $value ** 2, $values)));

    return array_map(fn (float $value): float => $value / $norm, $values);
}

$needsPostgres = fn (): bool => DB::connection()->getDriverName() !== 'pgsql';

it('creates the embeddings table only where pgvector is available', function () {
    // On SQLite the migration is a no-op instead of failing, and the similar
    // threads feature falls back to keyword search.
    expect(Schema::hasTable('thread_embeddings'))->toBe(DB::connection()->getDriverName() === 'pgsql');
});

it('stores and reads back a full-size embedding', function () {
    $thread = Thread::factory()->create();
    $vector = embeddingFixture(0.0);

    ThreadEmbedding::query()->create([
        'thread_id' => $thread->id,
        'embedding' => $vector,
        'model' => 'text-embedding-3-small',
        'content_hash' => hash('sha256', 'title and opening post'),
    ]);

    $stored = ThreadEmbedding::query()->with('thread')->findOrFail($thread->id);

    // pgvector stores float32, hence the tolerance.
    expect($stored->embedding)->toHaveCount(1536)
        ->and($stored->embedding)->toEqualWithDelta($vector, 1e-6)
        ->and($stored->thread->is($thread))->toBeTrue()
        ->and($stored->model)->toBe('text-embedding-3-small');
})->group('pgsql')->skip($needsPostgres, 'pgvector requires PostgreSQL');

it('indexes embeddings with HNSW for cosine similarity queries', function () {
    [$close, $far] = Thread::factory()->count(2)->create();
    ThreadEmbedding::query()->create(['thread_id' => $close->id, 'embedding' => embeddingFixture(0.0), 'model' => 'm', 'content_hash' => 'a']);
    ThreadEmbedding::query()->create(['thread_id' => $far->id, 'embedding' => embeddingFixture(1.5), 'model' => 'm', 'content_hash' => 'b']);

    $similar = ThreadEmbedding::query()
        ->whereVectorSimilarTo('embedding', embeddingFixture(0.05), minSimilarity: 0.9)
        ->pluck('thread_id');

    expect($similar->all())->toBe([$close->id])
        ->and(collect(Schema::getIndexes('thread_embeddings'))->pluck('type'))->toContain('hnsw');
})->group('pgsql')->skip($needsPostgres, 'pgvector requires PostgreSQL');
