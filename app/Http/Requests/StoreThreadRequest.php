<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Thread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new thread: its category, title, opening post and up to five tags.
 */
final class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        // A missing or unknown category is a validation error, not a refusal.
        return $category === null || ($this->user()?->can('create', [Thread::class, $category]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ThreadRules::title(),
            'body' => ThreadRules::body(),
            ...ThreadRules::tags(),
        ];
    }

    public function category(): ?Category
    {
        $id = filter_var($this->input('category_id'), FILTER_VALIDATE_INT);

        return $id === false ? null : Category::query()->find($id);
    }

    /**
     * @return list<int>
     */
    public function tagIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('tags', [])));
    }
}
