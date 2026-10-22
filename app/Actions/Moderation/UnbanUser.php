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
        if ($target->banned_at === null) {
            return;
        }

        DB::transaction(function () use ($target, $moderator): void {
            $target->forceFill([
                'banned_at' => null,
                'banned_until' => null,
                'ban_reason' => null,
            ])->save();

            $this->log->handle($moderator, ModerationActionType::UserUnbanned, $target);
        });
    }
}
