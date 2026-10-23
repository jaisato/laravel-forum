<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThreadResource;
use App\Models\Thread;
use App\Services\Ai\SimilarThreadFinder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The public threads most like this one (embeddings, or keyword search
 * without them), never itself.
 */
final class SimilarThreadController extends Controller
{
    /**
     * Threads similar to this one.
     *
     * @unauthenticated
     */
    public function __invoke(Thread $thread, SimilarThreadFinder $finder): AnonymousResourceCollection
    {
        Gate::authorize('view', $thread);

        $similar = new Collection($finder->similarTo($thread, (int) config('forum.ai.similar.limit'))->all());

        return ThreadResource::collection($similar->load(['author', 'category', 'tags', 'lastPost.author']));
    }
}
