<?php

declare(strict_types=1);

namespace App\Filament\Resources\Threads;

use App\Filament\Resources\Threads\Pages\ListThreads;
use App\Filament\Resources\Threads\Tables\ThreadsTable;
use App\Models\Thread;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Every thread, deleted ones included, for the staff to pin, lock, move,
 * delete or restore (spec §4.8). Threads are written in the forum.
 */
final class ThreadResource extends Resource
{
    protected static ?string $model = Thread::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): string
    {
        return __('Moderation');
    }

    public static function getModelLabel(): string
    {
        return __('thread');
    }

    public static function getPluralModelLabel(): string
    {
        return __('threads');
    }

    /**
     * @return Builder<Thread>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Thread> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['category', 'author']);
    }

    public static function table(Table $table): Table
    {
        return ThreadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThreads::route('/'),
        ];
    }
}
