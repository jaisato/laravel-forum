<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A thread in a listing. Expects the relations ThreadListing loads (author,
 * category, tags, lastPost.author) and reads the viewer's read marker and
 * the excerpt source when the query selected them.
 *
 * @mixin Thread
 */
final class ThreadResource extends JsonResource
{
    private const int EXCERPT_LENGTH = 200;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Thread $thread */
        $thread = $this->resource;

        return [
            'id' => $thread->id,
            'title' => $thread->title,
            'slug' => $thread->slug,
            'status' => $thread->status->value,
            'is_pinned' => $thread->is_pinned,
            'is_locked' => $thread->is_locked,
            'is_solved' => $thread->isSolved(),
            'is_unread' => $this->isUnread($thread, $request->user()),
            'replies_count' => $thread->replies_count,
            'views_count' => $thread->views_count,
            'created_at' => $thread->created_at?->toIso8601String(),
            'last_posted_at' => $thread->last_posted_at?->toIso8601String(),
            'excerpt' => $this->excerpt($thread),
            'author' => UserResource::orErased($thread->author)?->resolve($request),
            'last_poster' => UserResource::orErased($thread->lastPost?->author)?->resolve($request),
            'category' => [
                'id' => $thread->category->id,
                'name' => $thread->category->name,
                'slug' => $thread->category->slug,
                'color' => $thread->category->color,
            ],
            'tags' => $thread->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])->values()->all(),
        ];
    }

    /**
     * A thread is new to a member while it has posts they have not read. A
     * thread they never opened is new when its activity is more recent than
     * their account, so a newcomer does not find the whole archive unread.
     */
    private function isUnread(Thread $thread, ?User $viewer): bool
    {
        if ($viewer === null || $thread->last_post_id === null
            || ! array_key_exists('last_read_post_id', $thread->getAttributes())) {
            return false;
        }

        /** @var int|null $lastRead */
        $lastRead = $thread->getAttribute('last_read_post_id');

        if ($lastRead === null) {
            return $thread->last_posted_at !== null
                && $viewer->created_at !== null
                && $thread->last_posted_at->isAfter($viewer->created_at);
        }

        return $thread->last_post_id > $lastRead;
    }

    private function excerpt(Thread $thread): ?string
    {
        $source = $thread->getAttribute('excerpt_source');

        if (! is_string($source) || $source === '') {
            return null;
        }

        $excerpt = app(MarkdownRenderer::class)->excerpt($source, self::EXCERPT_LENGTH);

        return $excerpt === '' ? null : $excerpt;
    }
}
