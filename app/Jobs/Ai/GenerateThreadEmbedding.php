<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\ForumAi;
use App\Models\Thread;
use App\Models\ThreadEmbedding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

/**
 * Embeds a thread for the similar threads search: its title and the start
 * of its opening post. Content already embedded with the same model is not
 * sent again (content_hash) unless forced. Threads of any status are
 * embedded; the search shows public ones only.
 */
final class GenerateThreadEmbedding implements ShouldQueue
{
    use Queueable;

    /** How much of the opening post says what the thread is about. */
    private const int BODY_LENGTH = 2000;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Thread $thread,
        public readonly bool $force = false,
    ) {}

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ForumAi $ai): void
    {
        if (! $ai->usesVectorSearch()) {
            return;
        }

        $thread = Thread::query()->with('firstPost')->find($this->thread->id);

        if ($thread === null) {
            return;
        }

        $model = $ai->embeddingsModel();
        $text = trim($thread->title."\n\n".Str::limit((string) $thread->firstPost?->body, self::BODY_LENGTH, ''));
        $hash = hash('sha256', $model."\n".$text);

        if (! $this->force && ThreadEmbedding::query()->whereKey($thread->id)->where('content_hash', $hash)->exists()) {
            return;
        }

        $vector = Embeddings::for([$text])
            ->dimensions($ai->embeddingsDimensions())
            ->generate($ai->embeddingsProvider(), $model)
            ->first();

        ThreadEmbedding::query()->updateOrCreate(
            ['thread_id' => $thread->id],
            ['embedding' => $vector, 'model' => $model, 'content_hash' => $hash],
        );
    }
}
