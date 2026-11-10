<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;

final class SolutionAccepted extends PostNotification
{
    public function __construct(Post $post, public User $actor)
    {
        parent::__construct($post);
    }

    public function type(): string
    {
        return 'solution_accepted';
    }

    /**
     * Also dropped when another reply became the solution in the meantime,
     * and when its author already heard that this answer solved the thread:
     * unmarking it and marking it again (nothing limits how often) must not
     * send them a notification, and an email, every time.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return parent::shouldSend($notifiable, $channel)
            && (int) $this->thread()->fresh()?->solution_post_id === $this->post->id
            && ! $this->alreadyNotified($notifiable);
    }

    protected function emailPreference(): string
    {
        return 'email_solutions';
    }

    protected function title(): string
    {
        return __('Your answer in ":thread" was marked as the solution', ['thread' => $this->thread()->title]);
    }

    protected function actor(): User
    {
        return $this->actor;
    }
}
