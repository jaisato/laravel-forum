<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category with its public counters, plus its latest public thread when
 * the latestThread relation was loaded (home page).
 *
 * @mixin Category
 */
final class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'color' => $category->color,
            'icon' => $category->icon,
            'is_locked' => $category->is_locked,
            'threads_count' => $category->threads_count,
            'posts_count' => $category->posts_count,
            'latest_thread' => $this->whenLoaded('latestThread', fn (Thread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'slug' => $thread->slug,
                'last_posted_at' => $thread->last_posted_at?->toIso8601String(),
                'last_poster' => UserResource::orErased($thread->lastPost?->author)?->resolve($request),
            ]),
        ];
    }
}
