<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationActionType;
use Carbon\CarbonImmutable;
use Database\Factories\ModerationActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry of the moderation audit log: who did what, to which subject,
 * when and why. Entries are never updated.
 *
 * @property int $id
 * @property int|null $moderator_id
 * @property ModerationActionType $action
 * @property string $subject_type
 * @property int $subject_id
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['moderator_id', 'action', 'subject_type', 'subject_id', 'reason', 'meta'])]
class ModerationAction extends Model
{
    /** @use HasFactory<ModerationActionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ModerationActionType::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id')->withTrashed();
    }

    /**
     * The thread, post, report or user acted upon. Deleted subjects still
     * resolve, otherwise the log would lose the context of deletions.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
