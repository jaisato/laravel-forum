<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Queries\ThreadListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The thread listing's options in the API (GET /api/v1/threads): the web
 * pages' sorts and filters, plus the category and the tag the pages carry in
 * their path. Unlike the pages, which fall back to the defaults, the API
 * tells the client what it got wrong.
 */
final class ThreadIndexRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', 'string', Rule::in(ThreadListing::SORTS)],
            'filter' => ['sometimes', 'string', Rule::in(ThreadListing::FILTERS)],
            'category' => ['sometimes', 'string', Rule::exists('categories', 'slug')],
            'tag' => ['sometimes', 'string', Rule::exists('tags', 'slug')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user() === null && in_array($this->input('filter'), ThreadListing::PERSONAL_FILTERS, true)) {
                $validator->errors()->add('filter', __('Only members can use this filter.'));
            }
        }];
    }

    /**
     * @return array{sort: string, filter: string, category?: string, tag?: string}
     */
    public function listing(): array
    {
        $narrowed = array_filter([
            'category' => $this->validated('category'),
            'tag' => $this->validated('tag'),
        ], is_string(...));

        return [
            'sort' => (string) $this->validated('sort', ThreadListing::DEFAULT_SORT),
            'filter' => (string) $this->validated('filter', ThreadListing::DEFAULT_FILTER),
            ...$narrowed,
        ];
    }
}
