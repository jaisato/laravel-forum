<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReactionType;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Post $post */
        $post = $this->route('post');

        return $this->user()?->can('react', $post) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(ReactionType::class)]];
    }

    public function type(): ReactionType
    {
        return ReactionType::from((string) $this->validated('type'));
    }
}
