<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The members, for bans and roles (spec §4.8). Accounts are created by
 * signing up and erased from the member's own settings, never here.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'username';

    public static function getNavigationGroup(): string
    {
        return __('Moderation');
    }

    public static function getModelLabel(): string
    {
        return __('member');
    }

    public static function getPluralModelLabel(): string
    {
        return __('members');
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return $query->withCount(['threads', 'posts']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
