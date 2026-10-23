<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Settles a report, optionally hiding the reported post. The post has then
 * been dealt with, so every open report about it is closed as resolved.
 */
final class ResolveReport
{
    public function __construct(
        private readonly HidePost $hidePost,
        private readonly RecordModerationAction $log,
    ) {}

    public function handle(Report $report, User $moderator, bool $hidePost, ?string $note = null): void
    {
        DB::transaction(function () use ($report, $moderator, $hidePost, $note): void {
            // Every open report of the post is locked, in id order, before
            // any of them changes: two moderators resolving two reports of one
            // post queue up on the same rows instead of each holding one the
            // other needs (a deadlock).
            $open = Report::query()
                ->where('post_id', $report->post_id)
                ->where('status', ReportStatus::Open)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $report = $open->firstWhere('id', $report->id);

            if ($report === null) {
                return;
            }

            $closed = Report::query()
                ->whereKey($open->modelKeys())
                ->update([
                    'status' => ReportStatus::Resolved,
                    'resolved_by_id' => $moderator->id,
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                ]);

            $this->log->handle($moderator, ModerationActionType::ReportResolved, $report, $note, [
                'hide_post' => $hidePost,
                'reports_closed' => $closed,
            ]);

            $post = Post::withTrashed()->find($report->post_id);

            if ($hidePost && $post !== null) {
                $this->hidePost->handle($post, $moderator, $note);
            }
        });
    }
}
