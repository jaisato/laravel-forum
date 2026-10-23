<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * The limits of a post body (config forum.posts), for new posts, edits and
 * previews alike.
 */
final class PostBodyRules
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'min:'.(int) config('forum.posts.body_min'),
            'max:'.(int) config('forum.posts.body_max'),
        ];
    }
}
