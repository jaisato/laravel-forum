<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Thread;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reply, optionally quoting a published post of the same thread.
 */
final class StorePostRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        return $this->inspectVisible('reply', $this->thread());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => PostBodyRules::rules(),
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('posts', 'id')
                    ->where('thread_id', $this->thread()->id)
                    ->where('status', PostStatus::Published->value)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function thread(): Thread
    {
        /** @var Thread */
        return $this->route('thread');
    }
}
