<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\UpdatePost;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Queries\ThreadPosts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A single post: read (a notification names one), edited or deleted.
 * Deleting the opening post deletes its thread, as on the web.
 */
final class PostController extends Controller
{
    /**
     * A post, by its id.
     *
     * @unauthenticated
     */
    public function show(Request $request, Post $post, ThreadPosts $threadPosts): PostResource
    {
        Gate::authorize('view', $post);

        /** @var User|null $viewer */
        $viewer = $request->user();

        return new PostResource($threadPosts->one($post, $viewer));
    }

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $updatePost, ThreadPosts $threadPosts): PostResource
    {
        /** @var User $editor */
        $editor = $request->user();

        $updated = $updatePost->handle($post, $editor, (string) $request->validated('body'));

        return new PostResource($threadPosts->one($updated, $editor));
    }

    public function destroy(Request $request, Post $post, DeletePost $deletePost): Response
    {
        Gate::authorize('view', $post);
        Gate::authorize('delete', $post);

        /** @var User $actor */
        $actor = $request->user();

        $deletePost->handle($post, $actor);

        return response()->noContent();
    }
}
