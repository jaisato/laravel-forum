<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationActions;

use App\Filament\Resources\ModerationActions\Pages\ListModerationActions;
use App\Filament\Resources\ModerationActions\Tables\ModerationActionsTable;
use App\Models\ModerationAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The moderation audit log (spec §4.5): who did what, to which subject,
 * when and why. Read only: entries are never changed.
 */
final class ModerationActionResource extends Resource
{
    protected static ?string $model = ModerationAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string
    {
        return __('Moderation');
    }

    public static function getNavigationLabel(): string
    {
        return __('Moderation log');
    }

    public static function getModelLabel(): string
    {
        return __('log entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Moderation log');
    }

    /**
     * @return Builder<ModerationAction>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ModerationAction> $query */
        $query = parent::getEloquentQuery();

        return $query->with('moderator');
    }

    public static function table(Table $table): Table
    {
        return ModerationActionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModerationActions::route('/'),
        ];
    }
}
