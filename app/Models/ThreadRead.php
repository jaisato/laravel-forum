<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ThreadReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far a member has read a thread. Posts with an id above
 * last_read_post_id are unread for them.
 *
 * @property int $id
 * @property int $thread_id
 * @property int $user_id
 * @property int $last_read_post_id
 * @property CarbonImmutable $last_read_at
 */
#[Fillable(['thread_id', 'user_id', 'last_read_post_id', 'last_read_at'])]
class ThreadRead extends Model
{
    /** @use HasFactory<ThreadReadFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_post_id' => 'integer',
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
