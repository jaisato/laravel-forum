<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Actions;

use App\Actions\Moderation\DismissReport;
use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Filament\Staff;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * Closes a report as unfounded (DismissReport). A post held for review
 * (the AI held it) is published again unless the toggle says otherwise;
 * any other post stays as it is.
 */
final class DismissReportAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'dismiss';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Dismiss'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->authorize('resolve')
            ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
            ->modalDescription(function (Report $record): ?string {
                if (self::isHeld($record)) {
                    return null;
                }

                return __('The post stays as it is.');
            })
            ->schema([
                Toggle::make('approve_post')
                    ->label(__('Publish the post again'))
                    ->helperText(__('It was held for review: dismissing the report says it should not have been.'))
                    ->default(true)
                    ->visible(fn (Report $record): bool => self::isHeld($record)),
                Textarea::make('note')
                    ->label(__('Note'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Report $record, array $data, DismissReport $dismissReport): void {
                $dismissReport->handle(
                    $record,
                    Staff::current(),
                    filled($data['note'] ?? null) ? (string) $data['note'] : null,
                    (bool) ($data['approve_post'] ?? false),
                );

                $this->success();
            })
            ->successNotificationTitle(__('Report dismissed.'));
    }

    private static function isHeld(Report $record): bool
    {
        return $record->post?->status === PostStatus::PendingReview;
    }
}
