<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Thread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The title and tags of a thread; its body is the opening post, edited as
 * any other post.
 */
final class UpdateThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->thread()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ThreadRules::title(),
            ...ThreadRules::tags(),
        ];
    }

    public function thread(): Thread
    {
        /** @var Thread */
        return $this->route('thread');
    }

    /**
     * @return list<int>
     */
    public function tagIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('tags', [])));
    }
}
