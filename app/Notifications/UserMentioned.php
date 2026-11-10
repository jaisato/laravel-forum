<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;

final class UserMentioned extends PostNotification
{
    public function type(): string
    {
        return 'user_mentioned';
    }

    /**
     * Once per post: a mention removed in an edit and written again in the
     * next one does not tell the member twice.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return parent::shouldSend($notifiable, $channel) && ! $this->alreadyNotified($notifiable);
    }

    protected function emailPreference(): string
    {
        return 'email_mentions';
    }

    protected function title(): string
    {
        return __(':actor mentioned you in ":thread"', ['actor' => $this->post->author->name, 'thread' => $this->thread()->title]);
    }

    protected function actor(): ?User
    {
        return $this->post->author;
    }
}
