<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * The limits of a thread (config forum.threads), for new threads and edits
 * alike. The body of a thread is its opening post: it can be as long as any
 * post, but not as short.
 */
final class ThreadRules
{
    /**
     * @return list<string>
     */
    public static function title(): array
    {
        return [
            'required',
            'string',
            'min:'.(int) config('forum.threads.title_min'),
            'max:'.(int) config('forum.threads.title_max'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function body(): array
    {
        return [
            'required',
            'string',
            'min:'.(int) config('forum.threads.body_min'),
            'max:'.(int) config('forum.posts.body_max'),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function tags(): array
    {
        return [
            'tags' => ['nullable', 'array', 'max:'.(int) config('forum.threads.max_tags')],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }
}
