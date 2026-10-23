<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\ReportPost;
use App\Ai\Data\ModerationResult;
use App\Enums\ModerationVerdict;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * What the forum does with the AI moderator's verdict (spec §4.6): nothing
 * for "allow"; an AI report for the staff for "review", or for "block"
 * below the confidence threshold; above it the post is also held for review
 * (its author is told). The AI never deletes anything, and never softens a
 * moderator's decision (HidePost).
 */
final class ApplyModerationVerdict
{
    public function __construct(
        private readonly ReportPost $reportPost,
        private readonly HidePost $hidePost,
    ) {}

    public function handle(Post $post, ModerationResult $result): void
    {
        if ($result->verdict === ModerationVerdict::Allow) {
            return;
        }

        DB::transaction(function () use ($post, $result): void {
            $this->reportPost->forAi($post, $result->reportReason(), $result->reason, $result->confidence);

            if ($result->verdict === ModerationVerdict::Block
                && $result->confidence >= (float) config('forum.ai.moderation.hide_threshold')) {
                $this->hidePost->handle($post, null, $result->reason);
            }
        });
    }
}
