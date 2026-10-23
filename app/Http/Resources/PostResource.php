<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PostStatus;
use App\Enums\ReactionType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A post on its thread page, with what the viewer may do with it. Expects
 * the thread relation set (the page sets it on every post), the author and
 * replyTo.author relations and the Post::withReactionSummary() counters.
 *
 * @mixin Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        /** @var User|null $viewer */
        $viewer = $request->user();
        $thread = $post->thread;

        $may = fn (string $ability, mixed $subject = null): bool => $viewer !== null
            && Gate::forUser($viewer)->allows($ability, $subject ?? $post);

        // A deleted post is only restored, and only a post everybody can see
        // may be quoted (StorePostRequest checks reply_to_id the same way).
        $mayEdit = ! $post->trashed() && $may('update');
        $mayQuote = $thread !== null
            && ! $post->trashed()
            && $post->status === PostStatus::Published
            && $may('reply', $thread);

        return [
            'id' => $post->id,
            'thread_id' => $post->thread_id,
            'body_html' => $post->body_html,
            // The Markdown source, only for those who may edit or quote it.
            'body' => $this->when($mayEdit || $mayQuote, $post->body),
            'status' => $post->status->value,
            'is_first' => $post->isFirst(),
            'is_solution' => $thread !== null && (int) $thread->solution_post_id === $post->id,
            'is_deleted' => $post->trashed(),
            'created_at' => $post->created_at?->toIso8601String(),
            'edited_at' => $post->edited_at?->toIso8601String(),
            'author' => UserResource::orErased($post->author)?->resolve($request),
            'reply_to' => $this->replyTo($post, $viewer, $request),
            'reactions' => $this->reactions($post),
            'can' => [
                'update' => $mayEdit,
                'quote' => $mayQuote,
                'delete' => ! $post->trashed() && $may('delete'),
                'restore' => $post->trashed() && $may('restore'),
                'react' => $may('react'),
                'report' => $may('report'),
                'hide' => ! $post->trashed() && $post->status !== PostStatus::Hidden && $may('hide'),
                'approve' => ! $post->trashed() && $post->status !== PostStatus::Published && $may('approve'),
                'mark_solution' => $thread !== null
                    && ! $post->trashed()
                    && $post->status === PostStatus::Published
                    && ! $post->isFirst()
                    && (int) $thread->solution_post_id !== $post->id
                    && $may('markSolution', $thread),
            ],
        ];
    }

    /**
     * The quoted post, unless the viewer may no longer see it.
     *
     * @return array{id: int, author: array<string, mixed>|null}|null
     */
    private function replyTo(Post $post, ?User $viewer, Request $request): ?array
    {
        $quoted = $post->replyTo;

        if ($quoted === null || ! $quoted->isVisibleTo($viewer)) {
            return null;
        }

        return [
            'id' => $quoted->id,
            'author' => UserResource::orErased($quoted->author)?->resolve($request),
        ];
    }

    /**
     * Every reaction type, in a fixed order, with its count and whether the
     * viewer gave it.
     *
     * @return list<array{type: string, emoji: string, count: int, reacted: bool}>
     */
    private function reactions(Post $post): array
    {
        return array_map(fn (ReactionType $type): array => [
            'type' => $type->value,
            'emoji' => $type->emoji(),
            'count' => (int) ($post->getAttribute("{$type->value}_reactions_count") ?? 0),
            'reacted' => (bool) ($post->getAttribute("{$type->value}_reacted") ?? false),
        ], ReactionType::cases());
    }
}
