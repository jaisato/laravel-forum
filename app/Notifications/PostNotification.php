<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;

/**
 * A notification about a forum post. It is dropped when the post stopped
 * being public between the event and the delivery (deleted or hidden).
 */
abstract class PostNotification extends ForumNotification
{
    public function __construct(public Post $post) {}

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->post->fresh()?->isPublic() === true;
    }

    public function postId(): int
    {
        return $this->post->id;
    }

    protected function thread(): Thread
    {
        return $this->post->thread ?? Thread::withTrashed()->findOrFail($this->post->thread_id);
    }

    protected function excerpt(): ?string
    {
        return app(MarkdownRenderer::class)->excerpt($this->post->body, 140);
    }

    protected function url(): string
    {
        return $this->post->url();
    }
}
