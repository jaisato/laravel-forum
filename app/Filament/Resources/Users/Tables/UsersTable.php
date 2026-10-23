<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Filament\Resources\Users\Actions\BanUserAction;
use App\Filament\Resources\Users\Actions\ChangeRoleAction;
use App\Filament\Resources\Users\Actions\UnbanUserAction;
use App\Filament\Staff;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The members, newest first. Emails are personal data the moderators do
 * not need: only administrators see them.
 */
final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('username')
                    ->label(__('Username'))
                    ->description(fn (User $record): string => $record->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->visible(fn (): bool => Staff::current()->canAdminister()),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->color(fn (Role $state): string => match ($state) {
                        Role::Admin => 'danger',
                        Role::Moderator => 'warning',
                        Role::Member => 'gray',
                    }),
                TextColumn::make('ban')
                    ->label(__('Banned'))
                    ->badge()
                    ->color('danger')
                    ->state(fn (User $record): ?string => self::ban($record))
                    ->placeholder('—'),
                IconColumn::make('email_verified_at')
                    ->label(__('Verified'))
                    ->boolean(),
                TextColumn::make('posts_count')
                    ->label(__('Posts'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->date()
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label(__('Last seen'))
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('Role'))
                    ->options(Role::class),
                Filter::make('banned')
                    ->label(__('Banned now'))
                    ->query(function (Builder $query): Builder {
                        /** @var Builder<User> $query */
                        return $query->banned();
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    BanUserAction::make(),
                    UnbanUserAction::make(),
                    ChangeRoleAction::make(),
                ]),
            ]);
    }

    /**
     * The ban in force, if any: for good or until a date.
     */
    private static function ban(User $user): ?string
    {
        if (! $user->isBanned()) {
            return null;
        }

        return $user->banned_until === null
            ? __('For good')
            : __('Until :date', ['date' => $user->banned_until->isoFormat('lll')]);
    }
}
