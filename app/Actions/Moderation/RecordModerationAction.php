<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Appends an entry to the moderation log. A null moderator means the
 * system acted on its own (e.g. the AI moderator).
 */
final class RecordModerationAction
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function handle(?User $moderator, ModerationActionType $type, Model $subject, ?string $reason = null, array $meta = []): ModerationAction
    {
        return ModerationAction::query()->create([
            'moderator_id' => $moderator?->id,
            'action' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
