<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forum\ReplyToThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Queries\ThreadPosts;
use App\Services\ReadTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The posts of a thread, oldest first, and the replies to it.
 */
final class ThreadPostController extends Controller
{
    /**
     * A page of the posts the viewer may see; a member's become read, as on
     * the thread page.
     */
    public function index(Request $request, Thread $thread, ThreadPosts $threadPosts, ReadTracker $reads): AnonymousResourceCollection
    {
        Gate::authorize('view', $thread);

        /** @var User|null $viewer */
        $viewer = $request->user();
        $posts = $threadPosts->paginate($thread, $viewer);
        $lastShown = $posts->getCollection()->last();

        if ($viewer !== null && $lastShown instanceof Post) {
            $reads->markRead($thread, $viewer, $lastShown->id);
        }

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request, Thread $thread, ReplyToThread $replyToThread, ThreadPosts $threadPosts): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();
        $replyTo = $request->filled('reply_to_id')
            ? Post::query()->find((int) $request->validated('reply_to_id'))
            : null;

        $post = $replyToThread->handle($thread, $author, (string) $request->validated('body'), $replyTo);

        return (new PostResource($threadPosts->one($post, $author)))->response()->setStatusCode(201);
    }
}
