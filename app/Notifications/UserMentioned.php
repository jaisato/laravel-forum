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
