<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * A category's name, description, colour, icon and whether only the staff
 * may start threads in it. Its address (slug) is set once, from the name,
 * so links keep working after a rename.
 */
final class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(60),
            ColorPicker::make('color')
                ->label(__('Color'))
                ->required()
                ->regex('/^#[0-9a-fA-F]{6}$/')
                ->default('#6366f1'),
            Textarea::make('description')
                ->label(__('Description'))
                ->maxLength(300)
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('icon')
                ->label(__('Icon'))
                ->maxLength(40)
                ->helperText(__('The name of a Lucide icon, such as messages-square.')),
            Toggle::make('is_locked')
                ->label(__('Only the staff may start threads'))
                ->helperText(__('For announcements: members can still reply.')),
        ]);
    }
}
