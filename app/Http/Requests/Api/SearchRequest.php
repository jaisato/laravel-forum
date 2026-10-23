<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\SearchRequest as SearchPageRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A search through the API (GET /api/v1/search/threads and /posts): the
 * words, required, and optionally the category and the tag, by slug.
 */
final class SearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:'.SearchPageRequest::QUERY_MAX_LENGTH],
            'category' => ['sometimes', 'string', Rule::exists('categories', 'slug')],
            'tag' => ['sometimes', 'string', Rule::exists('tags', 'slug')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function words(): string
    {
        return (string) $this->validated('q');
    }

    /**
     * The category and tag the search is narrowed to, for ForumSearch.
     *
     * @return array{category: int|null, tag: int|null}
     */
    public function scope(): array
    {
        $category = $this->validated('category');
        $tag = $this->validated('tag');

        return [
            'category' => is_string($category) ? Category::query()->where('slug', $category)->value('id') : null,
            'tag' => is_string($tag) ? Tag::query()->where('slug', $tag)->value('id') : null,
        ];
    }

    /**
     * What the page links keep.
     *
     * @return array<string, string>
     */
    public function appends(): array
    {
        return array_filter([
            'q' => $this->words(),
            'category' => $this->validated('category'),
            'tag' => $this->validated('tag'),
        ], is_string(...));
    }
}
