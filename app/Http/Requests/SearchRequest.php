<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What /search looks for: the words, threads or posts, and a category and
 * a tag (by slug) to narrow it. Like the listings it never fails: an
 * unknown type, category or tag falls back to the default, since search
 * links get shared and bookmarked, and the words are cut to a sane length.
 */
final class SearchRequest extends FormRequest
{
    public const array TYPES = ['threads', 'posts'];

    public const string DEFAULT_TYPE = 'threads';

    /** More words than any search needs, and a bound on what the engine gets. */
    public const int QUERY_MAX_LENGTH = 200;

    private ?Category $category = null;

    private ?Tag $tag = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $words = $this->query('q');
        $type = $this->query('type');
        $category = $this->query('category');
        $tag = $this->query('tag');

        $this->category = is_string($category) ? Category::query()->where('slug', $category)->first() : null;
        $this->tag = is_string($tag) ? Tag::query()->where('slug', $tag)->first() : null;

        $this->merge([
            'q' => is_string($words) ? trim(mb_substr(trim($words), 0, self::QUERY_MAX_LENGTH)) : '',
            'type' => in_array($type, self::TYPES, true) ? $type : self::DEFAULT_TYPE,
            'category' => $this->category?->slug,
            'tag' => $this->tag?->slug,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['present', 'string', 'max:'.self::QUERY_MAX_LENGTH],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'category' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
        ];
    }

    /**
     * The search as the page shows it back.
     *
     * @return array{q: string, type: string, category: string|null, tag: string|null}
     */
    public function search(): array
    {
        return [
            'q' => (string) $this->validated('q'),
            'type' => (string) $this->validated('type'),
            'category' => $this->category?->slug,
            'tag' => $this->tag?->slug,
        ];
    }

    /**
     * The category and tag the search is narrowed to, for ForumSearch.
     *
     * @return array{category: int|null, tag: int|null}
     */
    public function scope(): array
    {
        return [
            'category' => $this->category?->id,
            'tag' => $this->tag?->id,
        ];
    }
}
