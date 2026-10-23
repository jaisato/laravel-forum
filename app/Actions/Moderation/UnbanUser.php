<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifts a ban (also cleans up one that already lapsed).
 */
final class UnbanUser
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(User $target, User $moderator): void
    {
        DB::transaction(function () use ($target, $moderator): void {
            // Decide on the stored row, locked: two moderators (or a double
            // click) may lift the same ban at once.
            $stored = User::withTrashed()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            if ($stored->banned_at !== null) {
                $stored->forceFill([
                    'banned_at' => null,
                    'banned_until' => null,
                    'ban_reason' => null,
                ])->save();

                $this->log->handle($moderator, ModerationActionType::UserUnbanned, $stored);
            }

            $target->setRawAttributes($stored->getAttributes(), sync: true);
        });
    }
}
