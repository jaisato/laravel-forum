<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ThreadIndexRequest;
use App\Http\Resources\TagResource;
use App\Http\Resources\ThreadResource;
use App\Models\Tag;
use App\Models\Thread;
use App\Queries\ThreadListing;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TagController extends Controller
{
    public function show(ThreadIndexRequest $request, Tag $tag, ThreadListing $listing): Response
    {
        $filters = $request->listing();

        return Inertia::render('threads/Index', [
            'context' => [
                'type' => 'tag',
                'tag' => (new TagResource($tag))->resolve($request),
            ],
            'filters' => $filters,
            'threads' => ThreadResource::collection($listing->paginate(
                [...$filters, 'tag' => $tag->slug],
                $request->user(),
            )),
            'can' => ['create_thread' => Gate::allows('createAny', Thread::class)],
        ]);
    }
}
