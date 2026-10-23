<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Ai\SuggestTags;
use App\Http\Requests\TagSuggestionRequest;
use App\Http\Resources\TagResource;
use Illuminate\Http\JsonResponse;

/**
 * "Suggest tags" on the new thread form: the forum's tags the AI finds for
 * what the member wrote so far.
 */
final class TagSuggestionController extends Controller
{
    public function __invoke(TagSuggestionRequest $request, SuggestTags $suggestTags): JsonResponse
    {
        $tags = $suggestTags->handle($request->string('title')->value(), $request->string('body')->value());

        return response()->json(['tags' => TagResource::collection($tags)->resolve($request)]);
    }
}
