<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The categories in the order the forum shows them, dragged to reorder. A
 * category that holds threads, deleted ones included, cannot be deleted
 * (CategoryPolicy): its threads would have nowhere to go.
 */
final class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->paginated(false)
            ->columns([
                ColorColumn::make('color')
                    ->label(__('Color')),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (Category $record): ?string => $record->description)
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('Address'))
                    ->prefix('/c/')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_locked')
                    ->label(__('Staff only'))
                    ->boolean(),
                TextColumn::make('threads_count')
                    ->label(__('Threads'))
                    ->numeric(),
                TextColumn::make('posts_count')
                    ->label(__('Posts'))
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
