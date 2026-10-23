<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Number;

/**
 * How the queue shows who reported, how sure the AI was and the state of a
 * report, the same in the table and on the report's page.
 */
final class ReportSummary
{
    public static function reportedBy(Report $report): string
    {
        if ($report->source === ReportSource::Ai) {
            return __('AI moderator (:confidence sure)', ['confidence' => self::confidence($report) ?? '?']);
        }

        return $report->reporter->username ?? '—';
    }

    public static function confidence(Report $report): ?string
    {
        return $report->ai_confidence === null
            ? null
            : (string) Number::percentage($report->ai_confidence * 100, locale: app()->getLocale());
    }

    public static function statusColor(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Open => 'warning',
            ReportStatus::Resolved => 'success',
            ReportStatus::Dismissed => 'gray',
        };
    }
}
