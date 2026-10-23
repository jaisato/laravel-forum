<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ai\ForumAi;
use App\Jobs\Ai\GenerateThreadEmbedding;
use App\Models\Thread;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queues the embedding of every thread: after turning similar threads by
 * AI on, or after changing the embeddings model (--force sends them all
 * again; without it, unchanged content is skipped by its hash).
 */
#[Description('Queue the embeddings of every thread for the similar threads search')]
final class EmbedThreadsCommand extends Command
{
    protected $signature = 'forum:embed-threads {--force : Embed every thread again, even if its content did not change}';

    public function handle(ForumAi $ai): int
    {
        if (! $ai->enabled('similar')) {
            $this->components->error('Similar threads by AI are off: set FORUM_AI_ENABLED and FORUM_AI_SIMILAR_ENABLED to true.');

            return self::FAILURE;
        }

        if (! $ai->usesVectorSearch()) {
            $this->components->error('Embeddings need PostgreSQL with pgvector; this database uses keyword search instead.');

            return self::FAILURE;
        }

        $queued = 0;

        Thread::query()->select('id')->chunkById(500, function (Collection $threads) use (&$queued): void {
            foreach ($threads as $thread) {
                GenerateThreadEmbedding::dispatch($thread, (bool) $this->option('force'));
                $queued++;
            }
        });

        $this->components->info("{$queued} threads queued for embedding.");

        return self::SUCCESS;
    }
}
