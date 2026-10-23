<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Actions\ApproveReportedPostAction;
use App\Filament\Resources\Reports\Actions\DismissReportAction;
use App\Filament\Resources\Reports\Actions\ResolveReportAction;
use App\Filament\Resources\Reports\ReportSummary;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The queue: open reports by default, the oldest first, so nothing waits
 * behind newer ones.
 */
final class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Reported'))
                    ->since()
                    ->sortable(),
                TextColumn::make('post.body')
                    ->label(__('Post'))
                    ->description(fn (Report $record): ?string => $record->post?->thread?->title)
                    ->limit(90)
                    ->wrap(),
                TextColumn::make('post.author.username')
                    ->label(__('Author')),
                TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reported_by')
                    ->label(__('Reported by'))
                    ->state(fn (Report $record): string => ReportSummary::reportedBy($record)),
                TextColumn::make('details')
                    ->label(__('Details'))
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (ReportStatus $state): string => ReportSummary::statusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ReportStatus::class)
                    ->default(ReportStatus::Open->value),
                SelectFilter::make('source')
                    ->label(__('Reported by'))
                    ->options(ReportSource::class),
                SelectFilter::make('reason')
                    ->label(__('Reason'))
                    ->options(ReportReason::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('viewPost')
                        ->label(__('View in the forum'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->visible(fn (Report $record): bool => $record->post !== null)
                        ->url(fn (Report $record): ?string => $record->post?->url())
                        ->openUrlInNewTab(),
                    ApproveReportedPostAction::make(),
                    ResolveReportAction::make(),
                    DismissReportAction::make(),
                ]),
            ]);
    }
}
