<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Actions\Forum\RestorePost;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\HidePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Queries\ThreadPosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Post moderation (staff, with the moderate ability): hiding, approving
 * and restoring. Every answer is the post as it stands.
 */
final class PostModerationController extends Controller
{
    public function hide(HidePostRequest $request, Post $post, HidePost $hidePost, ThreadPosts $threadPosts): PostResource
    {
        $moderator = $this->moderator($request);

        $hidePost->handle($post, $moderator, $request->reason());

        return new PostResource($threadPosts->one($post, $moderator));
    }

    public function approve(Request $request, Post $post, ApprovePost $approvePost, ThreadPosts $threadPosts): PostResource
    {
        Gate::authorize('approve', $post);

        $moderator = $this->moderator($request);

        $approvePost->handle($post, $moderator);

        return new PostResource($threadPosts->one($post, $moderator));
    }

    public function restore(Request $request, Post $post, RestorePost $restorePost, ThreadPosts $threadPosts): PostResource
    {
        Gate::authorize('restore', $post);

        $moderator = $this->moderator($request);

        $restorePost->handle($post, $moderator);

        return new PostResource($threadPosts->one($post, $moderator));
    }

    private function moderator(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
