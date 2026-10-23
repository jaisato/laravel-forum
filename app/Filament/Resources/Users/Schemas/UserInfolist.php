<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Filament\Staff;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A member at a glance: who they are, how active, and their ban if any.
 */
final class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Member'))
                ->columns(2)
                ->schema([
                    TextEntry::make('name')
                        ->label(__('Name')),
                    TextEntry::make('username')
                        ->label(__('Username')),
                    TextEntry::make('email')
                        ->label(__('Email'))
                        ->visible(fn (): bool => Staff::current()->canAdminister()),
                    TextEntry::make('role')
                        ->label(__('Role'))
                        ->badge()
                        ->color(fn (Role $state): string => match ($state) {
                            Role::Admin => 'danger',
                            Role::Moderator => 'warning',
                            Role::Member => 'gray',
                        }),
                    TextEntry::make('email_verified_at')
                        ->label(__('Email verified'))
                        ->dateTime()
                        ->placeholder(__('Not verified')),
                    TextEntry::make('created_at')
                        ->label(__('Joined'))
                        ->dateTime(),
                    TextEntry::make('last_seen_at')
                        ->label(__('Last seen'))
                        ->since()
                        ->placeholder('—'),
                    TextEntry::make('threads_count')
                        ->label(__('Threads'))
                        ->numeric(),
                    TextEntry::make('posts_count')
                        ->label(__('Posts'))
                        ->numeric(),
                ]),
            Section::make(__('The ban'))
                ->columns(2)
                ->visible(fn (User $record): bool => $record->banned_at !== null)
                ->schema([
                    TextEntry::make('banned_at')
                        ->label(__('Since'))
                        ->dateTime(),
                    TextEntry::make('banned_until')
                        ->label(__('Until'))
                        ->dateTime()
                        ->placeholder(__('For good')),
                    TextEntry::make('ban_reason')
                        ->label(__('Reason'))
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
