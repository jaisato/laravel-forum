<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Moderation\ReportPost;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class PostReportController extends Controller
{
    /**
     * Accepted for the staff's queue; reporting again while the report is
     * open keeps that one (ReportPost is idempotent).
     */
    public function store(StoreReportRequest $request, Post $post, ReportPost $reportPost): JsonResponse
    {
        /** @var User $reporter */
        $reporter = $request->user();

        $reportPost->handle($post, $reporter, $request->reason(), $request->details());

        return response()->json(['message' => __('Thanks, the moderators will review it.')], 202);
    }
}
