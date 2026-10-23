<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesNestedResources;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A private message on its conversation page. Expects the author relation.
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    use ResolvesNestedResources;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Message $message */
        $message = $this->resource;

        return [
            'id' => $message->id,
            'body_html' => $message->body_html,
            'created_at' => $message->created_at?->toIso8601String(),
            'author' => UserResource::orErased($message->author),
            'is_own' => $message->user_id === $request->user()?->getAuthIdentifier(),
        ];
    }
}
