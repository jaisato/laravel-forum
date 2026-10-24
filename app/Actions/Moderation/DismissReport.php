<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closes a single report as unfounded. The post stays as it is, unless the
 * staff member asks to publish again a post held for review (the AI held it
 * and the report says why: dismissing it says the AI was wrong). A post a
 * moderator hid stays hidden either way.
 */
final class DismissReport
{
    public function __construct(
        private readonly RecordModerationAction $log,
        private readonly ApprovePost $approvePost,
    ) {}

    public function handle(Report $report, User $moderator, ?string $note = null, bool $approvePost = false): void
    {
        DB::transaction(function () use ($report, $moderator, $note, $approvePost): void {
            // The thread before the report, the order of ResolveReport and of
            // the AI moderator refreshing its open report: locking the report
            // first and the thread only to approve the post could deadlock.
            $threadId = Post::withTrashed()->whereKey($report->post_id)->value('thread_id');

            if ($threadId !== null) {
                Thread::withTrashed()->whereKey($threadId)->lockForUpdate()->first();
            }

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

            $post = $approvePost ? Post::query()->find($report->post_id) : null;

            if ($post?->status === PostStatus::PendingReview) {
                $this->approvePost->handle($post, $moderator);
            }
        });
    }
}
