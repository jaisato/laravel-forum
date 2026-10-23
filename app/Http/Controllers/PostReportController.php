<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Moderation\ReportPost;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\StoreReportRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class PostReportController extends Controller
{
    use FlashesToasts;

    /**
     * Reporting twice keeps the first open report (ReportPost is idempotent).
     */
    public function store(StoreReportRequest $request, Post $post, ReportPost $reportPost): RedirectResponse
    {
        /** @var User $reporter */
        $reporter = $request->user();

        $reportPost->handle($post, $reporter, $request->reason(), $request->details());

        $this->toast('Thanks, the moderators will review it.');

        return back();
    }
}
