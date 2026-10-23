<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A reply in a private conversation. Those outside it get a 404, banned
 * participants a 403 (ConversationPolicy::reply).
 */
final class StoreMessageRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('reply', $this->conversation());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => MessageBodyRules::rules(),
        ];
    }

    public function conversation(): Conversation
    {
        /** @var Conversation */
        return $this->route('conversation');
    }
}
