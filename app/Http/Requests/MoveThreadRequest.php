<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Thread;
use Illuminate\Foundation\Http\FormRequest;

final class MoveThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Thread $thread */
        $thread = $this->route('thread');

        return $this->user()?->can('move', $thread) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['category_id' => ['required', 'integer', 'exists:categories,id']];
    }

    public function category(): Category
    {
        return Category::query()->findOrFail((int) $this->validated('category_id'));
    }
}
