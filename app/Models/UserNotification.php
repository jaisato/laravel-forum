<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A notification stored for a member (the database channel): the bell, the
 * notifications page and the API read these. Forum notifications also
 * record who caused them and the post they are about (ForumDatabaseChannel).
 *
 * @property string $id
 * @property string $type
 * @property int|null $actor_id
 * @property int|null $post_id
 * @property array{type?: string, title?: string, excerpt?: string|null, url?: string, actor?: array{username: string, avatar_url: string|null}|null} $data
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class UserNotification extends DatabaseNotification
{
    /**
     * The post it is about, deleted or not.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /**
     * Whether what the notification says must be kept from its recipient:
     * its post was hidden, held for review or deleted since, or its thread
     * was, and the recipient may no longer see it. Their own posts are not
     * withheld (a moderation notice is about one). Expects post.thread
     * loaded, deleted threads included.
     */
    public function isWithheldFrom(User $recipient): bool
    {
        if ($this->post_id === null) {
            return false;
        }

        $post = $this->post;

        if ($post === null) {
            return true;
        }

        if ($post->isAuthoredBy($recipient)) {
            return false;
        }

        return ! $post->isVisibleTo($recipient) || $post->thread?->isVisibleTo($recipient) !== true;
    }

    /**
     * Where the notification leads, as a path on this forum: notifications
     * keep the absolute address of the moment they were sent, which no
     * longer works once the forum changes address, and a redirect must
     * never leave the site.
     */
    public function path(): ?string
    {
        $url = $this->data['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        return '/'.ltrim($parts['path'] ?? '', '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
