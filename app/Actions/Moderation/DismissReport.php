<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes a single report as unfounded; the post stays as it is.
 */
final class DismissReport
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(Report $report, User $moderator, ?string $note = null): void
    {
        DB::transaction(function () use ($report, $moderator, $note): void {
            $report = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($report->status !== ReportStatus::Open) {
                return;
            }

            $report->forceFill([
                'status' => ReportStatus::Dismissed,
                'resolved_by_id' => $moderator->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
            ])->save();

            $this->log->handle($moderator, ModerationActionType::ReportDismissed, $report, $note);
        });
    }
}
