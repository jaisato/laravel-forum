<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forum\CreateThread;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\UpdateThread;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ThreadIndexRequest;
use App\Http\Requests\StoreThreadRequest;
use App\Http\Requests\UpdateThreadRequest;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use App\Queries\ThreadListing;
use App\Services\ThreadSubscriptions;
use App\Services\ViewCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Threads: listed and read by anyone, started, edited and deleted under the
 * same policies and actions as on the web.
 */
final class ThreadController extends Controller
{
    /**
     * The threads, sorted and narrowed like the web listings.
     *
     * @unauthenticated
     */
    public function index(ThreadIndexRequest $request, ThreadListing $listing): AnonymousResourceCollection
    {
        $filters = $request->listing();

        return ThreadResource::collection($listing->paginate($filters, $request->user())
            // The web pages carry these two in their path instead.
            ->appends(array_intersect_key($filters, array_flip(['category', 'tag']))));
    }

    /**
     * The thread and whether the member follows it; its posts are read
     * from /threads/{thread}/posts. The visit counts, as on the web.
     *
     * @unauthenticated
     */
    public function show(Request $request, Thread $thread, ViewCounter $views, ThreadSubscriptions $subscriptions): ThreadResource
    {
        Gate::authorize('view', $thread);

        /** @var User|null $viewer */
        $viewer = $request->user();

        $views->record($thread, $request);

        return ThreadResource::single($thread)->additional(['meta' => [
            'subscribed' => $viewer !== null && $subscriptions->isSubscribed($thread, $viewer),
        ]]);
    }

    public function store(StoreThreadRequest $request, CreateThread $createThread): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();
        /** @var Category $category */
        $category = $request->category();

        $thread = $createThread->handle(
            $author,
            $category,
            $request->string('title')->value(),
            $request->string('body')->value(),
            $request->tagIds(),
        );

        return ThreadResource::single($thread)->response()->setStatusCode(201);
    }

    /**
     * Changes the title, the tags or both: what is not sent stays.
     */
    public function update(UpdateThreadRequest $request, Thread $thread, UpdateThread $updateThread): ThreadResource
    {
        /** @var User $editor */
        $editor = $request->user();

        return ThreadResource::single($updateThread->handle($thread, $editor, $request->title(), $request->tagIds()));
    }

    public function destroy(Request $request, Thread $thread, DeleteThread $deleteThread): Response
    {
        Gate::authorize('view', $thread);
        Gate::authorize('delete', $thread);

        /** @var User $actor */
        $actor = $request->user();

        $deleteThread->handle($thread, $actor);

        return response()->noContent();
    }
}
