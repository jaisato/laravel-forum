<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\Schemas\ReportInfolist;
use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\Report;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The moderation queue (spec §4.5): reports from members and from the AI
 * moderator, open ones first in line. Reports are filed in the forum.
 */
final class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('Moderation');
    }

    public static function getModelLabel(): string
    {
        return __('report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reports');
    }

    /**
     * The open reports, so the queue shows what is waiting at a glance.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = Report::query()->where('status', ReportStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * @return Builder<Report>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Report> $query */
        $query = parent::getEloquentQuery();

        // Dotted keys: the table adds its own with('post') for its columns,
        // which would replace a constraint written on 'post' itself.
        return $query->with([
            'post.author',
            'post.thread' => fn ($thread) => $thread->withTrashed(),
            'reporter',
            'resolver',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
        ];
    }
}
