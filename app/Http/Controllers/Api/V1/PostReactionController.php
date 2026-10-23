<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forum\ReactToPost;
use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Queries\ThreadPosts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Reactions by type (PUT gives it, DELETE takes it back), idempotent: a
 * repeated request changes nothing and gets the same answer, the post's
 * reaction summary.
 */
final class PostReactionController extends Controller
{
    /**
     * Gives the reaction.
     *
     * @response array{data: list<array{type: string, emoji: string, count: int, reacted: bool}>}
     */
    public function update(Request $request, Post $post, ReactionType $type, ReactToPost $reactions, ThreadPosts $threadPosts): JsonResponse
    {
        $member = $this->member($request, $post);

        $reactions->give($post, $member, $type);

        return $this->summary($post, $member, $threadPosts);
    }

    /**
     * Takes the reaction back.
     *
     * @response array{data: list<array{type: string, emoji: string, count: int, reacted: bool}>}
     */
    public function destroy(Request $request, Post $post, ReactionType $type, ReactToPost $reactions, ThreadPosts $threadPosts): JsonResponse
    {
        $member = $this->member($request, $post);

        $reactions->takeBack($post, $member, $type);

        return $this->summary($post, $member, $threadPosts);
    }

    /**
     * The member reacting: posts they may not see answer 404 before the
     * reaction rules are asked.
     */
    private function member(Request $request, Post $post): User
    {
        Gate::authorize('view', $post);
        Gate::authorize('react', $post);

        /** @var User */
        return $request->user();
    }

    private function summary(Post $post, User $member, ThreadPosts $threadPosts): JsonResponse
    {
        return response()->json(['data' => PostResource::reactionSummary($threadPosts->one($post, $member))]);
    }
}
