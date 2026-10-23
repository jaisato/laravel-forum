<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Schemas;

use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\ReportSummary;
use App\Models\Report;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A report with the post it is about, whole, and the decision once there
 * is one. The post is shown as written (Markdown source, escaped).
 */
final class ReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('The report'))
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->label(__('Reported'))
                        ->dateTime(),
                    TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->color(fn (ReportStatus $state): string => ReportSummary::statusColor($state)),
                    TextEntry::make('reason')
                        ->label(__('Reason'))
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('reported_by')
                        ->label(__('Reported by'))
                        ->state(fn (Report $record): string => ReportSummary::reportedBy($record)),
                    TextEntry::make('confidence')
                        ->label(__('Confidence'))
                        ->visible(fn (Report $record): bool => $record->source === ReportSource::Ai)
                        ->state(fn (Report $record): ?string => ReportSummary::confidence($record)),
                    TextEntry::make('details')
                        ->label(__('Details'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
            Section::make(__('The reported post'))
                ->columns(2)
                ->schema([
                    TextEntry::make('post.author.username')
                        ->label(__('Author')),
                    TextEntry::make('post.status')
                        ->label(__('Status'))
                        ->badge(),
                    TextEntry::make('post.thread.title')
                        ->label(__('Thread'))
                        ->url(fn (Report $record): ?string => $record->post?->url())
                        ->openUrlInNewTab()
                        ->columnSpanFull(),
                    TextEntry::make('post.body')
                        ->label(__('Post'))
                        ->columnSpanFull(),
                ]),
            Section::make(__('The decision'))
                ->columns(2)
                ->visible(fn (Report $record): bool => $record->status !== ReportStatus::Open)
                ->schema([
                    TextEntry::make('resolver.username')
                        ->label(__('Decided by'))
                        ->placeholder('—'),
                    TextEntry::make('resolved_at')
                        ->label(__('Decided'))
                        ->dateTime(),
                    TextEntry::make('resolution_note')
                        ->label(__('Note'))
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
