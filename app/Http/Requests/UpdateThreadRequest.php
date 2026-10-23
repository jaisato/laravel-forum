<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Thread;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The title and tags of a thread; its body is the opening post, edited as
 * any other post. What is not sent stays as it is (API clients may change
 * the tags alone).
 */
final class UpdateThreadRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        return $this->inspectVisible('update', $this->thread());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', ...ThreadRules::title()],
            ...ThreadRules::tags(),
        ];
    }

    public function thread(): Thread
    {
        /** @var Thread */
        return $this->route('thread');
    }

    public function title(): ?string
    {
        $title = $this->validated('title');

        return is_string($title) ? $title : null;
    }

    /**
     * The tags, or null to keep them; an empty list removes them all.
     *
     * @return list<int>|null
     */
    public function tagIds(): ?array
    {
        return $this->has('tags')
            ? array_values(array_map(intval(...), (array) $this->validated('tags', [])))
            : null;
    }
}
