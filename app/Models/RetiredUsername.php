<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A username its member left behind, by changing it or erasing the account.
 * Mentions store links to /u/{username} and @username keeps notifying
 * whoever answers to it, so nobody else may take it: its owner may take it
 * back, an erased account keeps it for good, and its old address leads to
 * the member's current profile.
 *
 * @property int $id
 * @property string $username
 * @property int $user_id
 * @property CarbonImmutable $retired_at
 */
#[Fillable(['username', 'user_id'])]
class RetiredUsername extends Model
{
    public const CREATED_AT = 'retired_at';

    public const UPDATED_AT = null;

    /**
     * Retires the username the member is leaving, and gives them back the
     * one they take if it was theirs before.
     */
    public static function retire(User $member, string $leaving, ?string $taking = null): void
    {
        static::query()->updateOrCreate(['username' => $leaving], ['user_id' => $member->id]);

        if ($taking !== null) {
            static::query()->where('username', $taking)->where('user_id', $member->id)->delete();
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }
}
