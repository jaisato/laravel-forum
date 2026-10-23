<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesNestedResources;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report in the staff's queue (moderation API): why and by whom it was
 * filed, how it was settled, and the reported post in full, hidden or
 * deleted as it may be. Expects post.author, post.thread (deleted threads
 * included), reporter and resolver loaded.
 *
 * @mixin Report
 */
final class ReportResource extends JsonResource
{
    use ResolvesNestedResources;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Report $report */
        $report = $this->resource;
        $post = $report->post;

        return [
            'id' => $report->id,
            'source' => $report->source->value,
            'reason' => $report->reason->value,
            'details' => $report->details,
            'ai_confidence' => $report->ai_confidence,
            'status' => $report->status->value,
            'created_at' => $report->created_at?->toIso8601String(),
            'resolved_at' => $report->resolved_at?->toIso8601String(),
            'resolution_note' => $report->resolution_note,
            'reporter' => UserResource::orErased($report->reporter),
            'resolver' => UserResource::orErased($report->resolver),
            'post' => $post === null ? null : [
                'id' => $post->id,
                'status' => $post->status->value,
                'is_deleted' => $post->trashed(),
                'body_html' => $post->body_html,
                'created_at' => $post->created_at?->toIso8601String(),
                'author' => UserResource::orErased($post->author),
                'thread' => $post->thread === null ? null : [
                    'id' => $post->thread->id,
                    'title' => $post->thread->title,
                    'slug' => $post->thread->slug,
                    'is_deleted' => $post->thread->trashed(),
                ],
            ],
        ];
    }
}
