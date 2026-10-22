<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ThreadIndexRequest;
use App\Http\Resources\ThreadResource;
use App\Models\Thread;
use App\Queries\ThreadListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ThreadController extends Controller
{
    public function index(ThreadIndexRequest $request, ThreadListing $listing): Response
    {
        $filters = $request->listing();

        return Inertia::render('threads/Index', [
            'context' => null,
            'filters' => $filters,
            'threads' => ThreadResource::collection($listing->paginate($filters, $request->user())),
        ]);
    }

    public function show(Request $request, Thread $thread): Response
    {
        Gate::authorize('view', $thread);

        $thread->load(['author', 'category', 'tags', 'lastPost.author']);

        return Inertia::render('threads/Show', [
            'thread' => (new ThreadResource($thread))->resolve($request),
        ]);
    }
}
