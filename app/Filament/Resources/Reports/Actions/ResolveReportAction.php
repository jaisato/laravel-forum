<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Actions;

use App\Actions\Moderation\ResolveReport;
use App\Enums\ReportStatus;
use App\Filament\Staff;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

/**
 * Upholds a report (ResolveReport), hiding the post if the moderator says
 * so. Every open report of the post closes with it.
 */
final class ResolveReportAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'resolve';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Resolve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('resolve')
            ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
            ->modalDescription(__('Every open report of this post is closed.'))
            ->schema([
                Toggle::make('hide_post')
                    ->label(__('Hide the post'))
                    ->helperText(__('Only the staff will see it. Its author is told.')),
                Textarea::make('note')
                    ->label(__('Note'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Report $record, array $data, ResolveReport $resolveReport): void {
                $resolveReport->handle(
                    $record,
                    Staff::current(),
                    (bool) ($data['hide_post'] ?? false),
                    filled($data['note'] ?? null) ? (string) $data['note'] : null,
                );

                $this->success();
            })
            ->successNotificationTitle(__('Report resolved.'));
    }
}
