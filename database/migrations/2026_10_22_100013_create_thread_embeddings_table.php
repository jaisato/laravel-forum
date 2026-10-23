<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Embeddings need pgvector, so the table only exists on PostgreSQL. On
     * other drivers (SQLite in local tests) the similar threads feature falls
     * back to keyword search and this migration does nothing.
     *
     * The dimension is fixed here: switching to an embeddings model with a
     * different size needs a new migration and a re-index.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::ensureVectorExtensionExists();

        Schema::create('thread_embeddings', function (Blueprint $table) {
            $table->foreignId('thread_id')->primary()->constrained()->cascadeOnDelete();
            // HNSW index with cosine distance, the metric of whereVectorSimilarTo().
            $table->vector('embedding', dimensions: config('forum.ai.similar.dimensions'))->index();
            $table->string('model', 100);
            // sha256 of the embedded text: unchanged content is never re-embedded.
            $table->string('content_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_embeddings');
    }
};
