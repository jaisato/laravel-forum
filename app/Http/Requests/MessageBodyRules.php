<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * The limits of a private message (config forum.messages), for the first
 * message of a conversation and for replies alike.
 */
final class MessageBodyRules
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'max:'.(int) config('forum.messages.body_max'),
        ];
    }
}
