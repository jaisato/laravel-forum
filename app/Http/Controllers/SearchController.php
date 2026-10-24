<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\PostSummaryResource;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Models\Tag;
use App\Services\Search\ForumSearch;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The search page (spec §4.2): threads or posts, narrowed to a category
 * and a tag. Nothing is searched until there are words to look for.
 */
final class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, ForumSearch $search): Response
    {
        $filters = $request->search();
        $searching = $filters['q'] !== '';
        $perPage = (int) config('forum.pagination.search');

        // Only what changes the search travels in the page links.
        $appends = array_filter([
            'q' => $filters['q'],
            'type' => $filters['type'] === SearchRequest::DEFAULT_TYPE ? null : $filters['type'],
            'category' => $filters['category'],
            'tag' => $filters['tag'],
        ]);

        return Inertia::render('search/Index', [
            'filters' => $filters,
            'threads' => $searching && $filters['type'] === 'threads'
                ? ThreadResource::collection($search->threads($filters['q'], $request->scope(), $perPage, $request->user())->appends($appends))
                : null,
            'posts' => $searching && $filters['type'] === 'posts'
                ? PostSummaryResource::collection($search->posts($filters['q'], $request->scope(), $perPage)->appends($appends))
                : null,
            'categories' => Category::query()->ordered()->get(['name', 'slug'])
                ->map(fn (Category $category): array => ['name' => $category->name, 'slug' => $category->slug])
                ->all(),
            'tags' => Tag::query()->orderBy('name')->get(['name', 'slug'])
                ->map(fn (Tag $tag): array => ['name' => $tag->name, 'slug' => $tag->slug])
                ->all(),
        ]);
    }
}
