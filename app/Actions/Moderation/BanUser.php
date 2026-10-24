<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Bans a user, permanently or until a date. Their API tokens are revoked;
 * on the web they can still read, and the policies keep them from writing
 * (User::canParticipate()). Banning again replaces the reason and the end
 * date.
 */
final class BanUser
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(User $target, User $moderator, string $reason, ?CarbonInterface $until = null): void
    {
        DB::transaction(function () use ($target, $moderator, $reason, $until): void {
            $target->forceFill([
                'banned_at' => now(),
                'banned_until' => $until,
                'ban_reason' => $reason,
            ])->save();

            $target->tokens()->delete();

            $this->log->handle($moderator, ModerationActionType::UserBanned, $target, $reason, [
                'until' => $until?->toIso8601String(),
            ]);
        });
    }
}
