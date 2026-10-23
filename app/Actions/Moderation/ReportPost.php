<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Events\PostReported;
use App\Models\Post;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Files a report for the staff queue. While a report is open, reporting the
 * same post again returns it instead of filing a duplicate; the partial
 * unique indexes settle concurrent requests.
 */
final class ReportPost
{
    public function handle(Post $post, User $reporter, ReportReason $reason, ?string $details = null): Report
    {
        return DB::transaction(function () use ($post, $reporter, $reason, $details): Report {
            // The request was authorised on the copies it loaded: a moderator
            // may have hidden the post or the thread since.
            $thread = Thread::withTrashed()->whereKey($post->thread_id)->sharedLock()->firstOrFail();
            $stored = Post::withTrashed()->whereKey($post->id)->sharedLock()->firstOrFail();
            $stored->setRelation('thread', $thread);

            if (! $stored->mayBeReportedBy($reporter->current())) {
                throw new AuthorizationException(__('This post can no longer be reported.'));
            }

            return $this->file($stored, $reporter->id, [
                'reporter_id' => $reporter->id,
                'source' => ReportSource::User,
                'reason' => $reason,
                'details' => $details,
            ]);
        });
    }

    /**
     * A report raised by the AI moderator: no reporter, with its confidence.
     */
    public function forAi(Post $post, ReportReason $reason, string $details, float $confidence): Report
    {
        return $this->file($post, null, [
            'reporter_id' => null,
            'source' => ReportSource::Ai,
            'reason' => $reason,
            'details' => $details,
            'ai_confidence' => $confidence,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function file(Post $post, ?int $reporterId, array $attributes): Report
    {
        $open = $this->openReport($post, $reporterId);

        if ($open !== null) {
            return $open;
        }

        try {
            // A savepoint: losing the race must not break an outer transaction.
            $report = DB::transaction(fn (): Report => $post->reports()->create($attributes));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->openReport($post, $reporterId) ?? throw $exception;
        }

        PostReported::dispatch($report);

        return $report;
    }

    private function openReport(Post $post, ?int $reporterId): ?Report
    {
        return $post->reports()
            ->where('status', ReportStatus::Open)
            ->when(
                $reporterId === null,
                fn (Builder $query) => $query->where('source', ReportSource::Ai),
                fn (Builder $query) => $query->where('reporter_id', $reporterId),
            )
            ->first();
    }
}
