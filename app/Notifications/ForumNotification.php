<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What every forum notification shares: stored in the database for the bell
 * and the notifications page, emailed when the member asked for that kind
 * of email and verified their address, and written in the recipient's
 * language (User::preferredLocale()).
 */
abstract class ForumNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Stable name stored as the notification type (not the class name).
     */
    abstract public function type(): string;

    /**
     * The user preference that turns the email on.
     */
    abstract protected function emailPreference(): string;

    abstract protected function title(): string;

    abstract protected function excerpt(): ?string;

    abstract protected function url(): string;

    abstract protected function actor(): ?User;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->hasVerifiedEmail() && $notifiable->wantsEmail($this->emailPreference())
            ? ['database', 'mail']
            : ['database'];
    }

    public function databaseType(User $notifiable): string
    {
        return $this->type();
    }

    /**
     * @return array{type: string, title: string, excerpt: string|null, url: string, actor: array{username: string, avatar_url: string|null}|null}
     */
    public function toArray(User $notifiable): array
    {
        $actor = $this->actor();

        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'excerpt' => $this->excerpt(),
            'url' => $this->url(),
            'actor' => $actor === null ? null : [
                'username' => $actor->username,
                'avatar_url' => $actor->avatar_url,
            ],
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $excerpt = $this->excerpt();

        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->title())
            ->when($excerpt !== null && $excerpt !== '', fn (MailMessage $mail): MailMessage => $mail->line((string) $excerpt))
            ->action(__('View in the forum'), $this->url())
            ->line(__('You can choose which emails you receive in your notification settings.'));
    }
}
