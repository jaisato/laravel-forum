<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SearchRequest;
use App\Http\Resources\PostSummaryResource;
use App\Http\Resources\ThreadResource;
use App\Services\Search\ForumSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Searching threads and posts. Only public content is ever found, whoever
 * searches (ForumSearch), as on the web.
 */
final class SearchController extends Controller
{
    public function threads(SearchRequest $request, ForumSearch $search): AnonymousResourceCollection
    {
        return ThreadResource::collection($search
            ->threads($request->words(), $request->scope(), (int) config('forum.pagination.search'))
            ->appends($request->appends()));
    }

    public function posts(SearchRequest $request, ForumSearch $search): AnonymousResourceCollection
    {
        return PostSummaryResource::collection($search
            ->posts($request->words(), $request->scope(), (int) config('forum.pagination.search'))
            ->appends($request->appends()));
    }
}
