<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The reply chosen as solution: a published reply of the same thread, not
 * its opening post.
 */
final class MarkSolutionRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        return $this->inspectVisible('markSolution', $this->thread());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $thread = $this->thread();

        return [
            'post_id' => [
                'required',
                'integer',
                Rule::exists('posts', 'id')
                    ->where('thread_id', $thread->id)
                    ->where('status', PostStatus::Published->value)
                    ->whereNull('deleted_at'),
                Rule::notIn([(int) $thread->first_post_id]),
            ],
        ];
    }

    public function thread(): Thread
    {
        /** @var Thread */
        return $this->route('thread');
    }

    public function answer(): Post
    {
        return Post::query()->findOrFail((int) $this->validated('post_id'));
    }
}
