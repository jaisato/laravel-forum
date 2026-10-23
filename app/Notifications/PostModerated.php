<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

/**
 * Tells authors that their post was held, hidden or approved. The moderator
 * stays anonymous.
 */
final class PostModerated extends PostNotification
{
    public function __construct(Post $post, public PostStatus $status, public ?string $reason = null)
    {
        parent::__construct($post);
    }

    public function type(): string
    {
        return 'post_moderated';
    }

    /**
     * Dropped when the post changed status again before delivery.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $this->post->fresh()?->status === $this->status;
    }

    protected function emailPreference(): string
    {
        return 'email_moderation';
    }

    protected function title(): string
    {
        $replace = ['thread' => $this->thread()->title];

        return match ($this->status) {
            PostStatus::Hidden => __('Your post in ":thread" was hidden by the moderators', $replace),
            PostStatus::PendingReview => __('Your post in ":thread" is awaiting review', $replace),
            PostStatus::Published => __('Your post in ":thread" was approved', $replace),
        };
    }

    protected function excerpt(): ?string
    {
        return $this->reason ?? parent::excerpt();
    }

    protected function actor(): ?User
    {
        return null;
    }
}
