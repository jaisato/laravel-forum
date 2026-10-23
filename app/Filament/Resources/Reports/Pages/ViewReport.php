<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\Actions\ApproveReportedPostAction;
use App\Filament\Resources\Reports\Actions\DismissReportAction;
use App\Filament\Resources\Reports\Actions\ResolveReportAction;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

final class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPost')
                ->label(__('View in the forum'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (Report $record): bool => $record->post !== null)
                ->url(fn (Report $record): ?string => $record->post?->url())
                ->openUrlInNewTab(),
            ApproveReportedPostAction::make(),
            ResolveReportAction::make(),
            DismissReportAction::make(),
        ];
    }
}
