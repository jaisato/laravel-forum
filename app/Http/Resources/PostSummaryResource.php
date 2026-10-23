<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A post in a list, away from its thread page: a member's replies on their
 * profile and the posts a search finds. Expects the author and thread
 * relations.
 *
 * @mixin Post
 */
final class PostSummaryResource extends JsonResource
{
    private const int EXCERPT_LENGTH = 200;

    /**
     * Characters of the post read for the excerpt, as listings do for the
     * opening post: a long post costs no more than a short one.
     */
    private const int EXCERPT_SOURCE_LENGTH = 600;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'excerpt' => app(MarkdownRenderer::class)->excerpt(mb_substr($post->body, 0, self::EXCERPT_SOURCE_LENGTH), self::EXCERPT_LENGTH),
            'status' => $post->status->value,
            'created_at' => $post->created_at?->toIso8601String(),
            'author' => UserResource::orErased($post->author)?->resolve($request),
            'thread' => [
                'id' => $post->thread->id,
                'title' => $post->thread->title,
                'slug' => $post->thread->slug,
            ],
        ];
    }
}
