<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags;

use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The forum's tags: administrators only (spec §4.8). Like categories, a tag
 * keeps the address (slug) it got when it was created.
 */
final class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Everyone may browse the tags in the forum (TagPolicy::viewAny);
     * managing them is for whoever may create them.
     */
    public static function canAccess(): bool
    {
        return self::canCreate();
    }

    public static function getNavigationGroup(): string
    {
        return __('Forum');
    }

    public static function getModelLabel(): string
    {
        return __('tag');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tags');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(40)
                ->unique(ignoreRecord: true),
            ColorPicker::make('color')
                ->label(__('Color'))
                ->regex('/^#[0-9a-fA-F]{6}$/'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('color')
                    ->label(__('Color')),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('Address'))
                    ->prefix('/tags/')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('threads_count')
                    ->label(__('Threads'))
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
