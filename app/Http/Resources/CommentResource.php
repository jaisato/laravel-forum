<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // whenLoaded, the way PostResource already does it. Reading $this->user
        // and $this->post unconditionally makes Eloquent lazy-load each one, so
        // serialising a page of comments fired two extra queries per comment -
        // and it did so silently, since the resource looks the same either way.
        // Now the relations appear when the caller has eager-loaded them and are
        // left out when it has not.
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => UserResource::make($this->user)),
            'post' => $this->whenLoaded('post', fn () => PostResource::make($this->post)),
            'body' => $this->body,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
