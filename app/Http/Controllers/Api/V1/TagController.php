<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The tags, by name; their threads are listed by GET /threads?tag=.
 */
final class TagController extends Controller
{
    /**
     * The tags, by name.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::query()->orderBy('name')->get());
    }

    /**
     * A tag, by its slug.
     *
     * @unauthenticated
     */
    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag);
    }
}
