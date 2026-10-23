<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Support\Markdown\MarkdownRenderer;

final class NewPrivateMessage extends ForumNotification
{
    public const string TYPE = 'new_private_message';

    public function __construct(public Message $message) {}

    public function type(): string
    {
        return self::TYPE;
    }

    /**
     * Dropped for members who left the conversation before delivery.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->message->conversation->participants()
            ->whereKey($notifiable->id)
            ->wherePivotNull('left_at')
            ->exists();
    }

    protected function emailPreference(): string
    {
        return 'email_messages';
    }

    protected function title(): string
    {
        return __(':actor sent you a private message: ":subject"', [
            'actor' => $this->message->author->name,
            'subject' => $this->message->conversation->subject,
        ]);
    }

    protected function excerpt(): string
    {
        return app(MarkdownRenderer::class)->excerpt($this->message->body, 140);
    }

    protected function url(): string
    {
        return $this->message->conversation->url();
    }

    protected function actor(): ?User
    {
        return $this->message->author;
    }
}
