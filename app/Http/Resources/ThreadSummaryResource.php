<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Thread;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A thread's AI summary in the API, rendered with the posts' sanitising
 * Markdown (the model's output is not trusted), and whether it is stale.
 *
 * @mixin Thread
 */
final class ThreadSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Thread $thread */
        $thread = $this->resource;

        return [
            'summary_html' => app(MarkdownRenderer::class)->render((string) $thread->ai_summary),
            'generated_at' => $thread->ai_summary_generated_at?->toIso8601String(),
            'stale' => $thread->summaryIsStale(),
        ];
    }
}
