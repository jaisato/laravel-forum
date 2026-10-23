<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Ai\ForumAi;
use App\Models\Thread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new thread form asks for tags: its title and body so far, from a
 * member who may start threads. Not found while AI tag suggestions are off.
 */
final class TagSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(app(ForumAi::class)->enabled('tags'), 404);

        return $this->user()?->can('createAny', Thread::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.(int) config('forum.threads.title_max')],
            'body' => ['nullable', 'string', 'max:'.(int) config('forum.posts.body_max')],
        ];
    }
}
