<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post flagged for the staff queue, by a member or by the AI moderator.
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $reporter_id
 * @property ReportSource $source
 * @property ReportReason $reason
 * @property string|null $details
 * @property float|null $ai_confidence
 * @property ReportStatus $status
 * @property int|null $resolved_by_id
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['post_id', 'reporter_id', 'source', 'reason', 'details', 'ai_confidence'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ReportSource::class,
            'reason' => ReportReason::class,
            'ai_confidence' => 'float',
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * The reported post, even after its author deleted it: moderators still
     * need to see what was reported.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    /**
     * The moderator who resolved or dismissed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id')->withTrashed();
    }
}
