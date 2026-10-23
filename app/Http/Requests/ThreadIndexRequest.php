<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\ThreadListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The sort and filter of a thread listing. Unknown values fall back to the
 * defaults instead of failing: listing URLs get shared and bookmarked, and
 * a stale or mistyped one should still show threads.
 */
final class ThreadIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sort = $this->query('sort');
        $filter = $this->query('filter');

        $personal = in_array($filter, ThreadListing::PERSONAL_FILTERS, true);

        $this->merge([
            'sort' => in_array($sort, ThreadListing::SORTS, true) ? $sort : ThreadListing::DEFAULT_SORT,
            'filter' => in_array($filter, ThreadListing::FILTERS, true) && ($this->user() !== null || ! $personal)
                ? $filter
                : ThreadListing::DEFAULT_FILTER,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort' => ['required', 'string', Rule::in(ThreadListing::SORTS)],
            'filter' => ['required', 'string', Rule::in(ThreadListing::FILTERS)],
        ];
    }

    /**
     * @return array{sort: string, filter: string}
     */
    public function listing(): array
    {
        return [
            'sort' => (string) $this->validated('sort'),
            'filter' => (string) $this->validated('filter'),
        ];
    }
}
