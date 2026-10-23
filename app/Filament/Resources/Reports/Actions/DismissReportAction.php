<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Actions;

use App\Actions\Moderation\DismissReport;
use App\Enums\ReportStatus;
use App\Filament\Staff;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Closes a report as unfounded (DismissReport); the post stays as it is.
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
            ->modalDescription(__('The post stays as it is.'))
            ->schema([
                Textarea::make('note')
                    ->label(__('Note'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Report $record, array $data, DismissReport $dismissReport): void {
                $dismissReport->handle($record, Staff::current(), filled($data['note'] ?? null) ? (string) $data['note'] : null);

                $this->success();
            })
            ->successNotificationTitle(__('Report dismissed.'));
    }
}
