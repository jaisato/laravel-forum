<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ThreadIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Queries\ThreadListing;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    public function show(ThreadIndexRequest $request, Category $category, ThreadListing $listing): Response
    {
        $filters = $request->listing();

        return Inertia::render('threads/Index', [
            'context' => [
                'type' => 'category',
                'category' => (new CategoryResource($category))->resolve($request),
            ],
            'filters' => $filters,
            'threads' => ThreadResource::collection($listing->paginate(
                [...$filters, 'category' => $category->slug],
                $request->user(),
            )),
        ]);
    }
}
