<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Actions\Moderation\DismissReport;
use App\Actions\Moderation\ResolveReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListReportsRequest;
use App\Http\Requests\Api\SettleReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The report queue (staff, with the moderate ability): the open reports,
 * oldest first, or those settled, latest first; resolving one (and every
 * other open report of its post, optionally hiding the post) or dismissing
 * it. Settling a report that is no longer open changes nothing.
 */
final class ReportController extends Controller
{
    public function index(ListReportsRequest $request): AnonymousResourceCollection
    {
        $status = $request->status();

        $reports = $this->withPost(Report::query())
            ->where('status', $status)
            ->when(
                $status === ReportStatus::Open,
                fn (Builder $reports) => $reports->orderBy('id'),
                fn (Builder $reports) => $reports->orderByDesc('resolved_at')->orderByDesc('id'),
            )
            ->paginate((int) config('forum.pagination.reports'))
            ->appends(array_filter(['status' => $status === ReportStatus::Open ? null : $status->value]));

        return ReportResource::collection($reports);
    }

    public function resolve(SettleReportRequest $request, Report $report, ResolveReport $resolveReport): ReportResource
    {
        /** @var User $moderator */
        $moderator = $request->user();

        $resolveReport->handle($report, $moderator, $request->boolean('hide_post'), $request->note());

        return $this->settled($report);
    }

    public function dismiss(SettleReportRequest $request, Report $report, DismissReport $dismissReport): ReportResource
    {
        /** @var User $moderator */
        $moderator = $request->user();

        $dismissReport->handle($report, $moderator, $request->note());

        return $this->settled($report);
    }

    private function settled(Report $report): ReportResource
    {
        return new ReportResource($this->withPost(Report::query())->findOrFail($report->id));
    }

    /**
     * @param  Builder<Report>  $reports
     * @return Builder<Report>
     */
    private function withPost(Builder $reports): Builder
    {
        return $reports->with([
            'post.author',
            'post.thread' => fn (BelongsTo $threads) => $threads->withTrashed(),
            'reporter',
            'resolver',
        ]);
    }
}
