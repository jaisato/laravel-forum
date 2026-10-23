<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

final class HidePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Post $post */
        $post = $this->route('post');

        return $this->user()?->can('hide', $post) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
