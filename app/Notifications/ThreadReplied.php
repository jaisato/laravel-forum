<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;

final class ThreadReplied extends PostNotification
{
    public function type(): string
    {
        return 'thread_replied';
    }

    protected function emailPreference(): string
    {
        return 'email_replies';
    }

    protected function title(): string
    {
        return __(':actor replied to ":thread"', ['actor' => $this->post->author->name, 'thread' => $this->thread()->title]);
    }

    protected function actor(): ?User
    {
        return $this->post->author;
    }
}
