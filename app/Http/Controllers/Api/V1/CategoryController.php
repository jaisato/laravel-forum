<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The categories, in the order the staff gave them, each with its latest
 * public thread; their threads are listed by GET /threads?category=.
 */
final class CategoryController extends Controller
{
    /**
     * The categories, in order.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->ordered()->withLatestThread()->get());
    }

    /**
     * A category, by its slug.
     *
     * @unauthenticated
     */
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource(Category::query()->whereKey($category->id)->withLatestThread()->sole());
    }
}
