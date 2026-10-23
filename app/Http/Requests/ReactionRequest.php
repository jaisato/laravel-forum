<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReactionType;
use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Post;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReactionRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        /** @var Post $post */
        $post = $this->route('post');

        return $this->inspectVisible('react', $post);
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
