<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Queries\ThreadListing;
use App\Services\ForumStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    private const int LATEST_THREADS = 10;

    public function index(Request $request, ThreadListing $listing, ForumStats $stats): Response
    {
        $categories = Category::query()
            ->ordered()
            ->withLatestThread()
            ->get();

        return Inertia::render('Home', [
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'threads' => ThreadResource::collection($listing->latest($request->user(), self::LATEST_THREADS))->resolve($request),
            'stats' => $stats->snapshot(),
        ]);
    }
}
