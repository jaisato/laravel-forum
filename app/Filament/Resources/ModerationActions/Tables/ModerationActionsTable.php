<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationActions\Tables;

use App\Enums\ModerationActionType;
use App\Models\ModerationAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The log, newest first. Entries without a moderator were taken by the
 * system: the AI moderator holding a post, or the console.
 */
final class ModerationActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('moderator.username')
                    ->label(__('Moderator'))
                    ->placeholder(__('System')),
                TextColumn::make('action')
                    ->label(__('Action'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('target')
                    ->label(__('Target'))
                    ->state(fn (ModerationAction $record): string => self::target($record)),
                TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('Action'))
                    ->options(ModerationActionType::class),
            ]);
    }

    /**
     * What the entry is about, by kind and number: the subject may be gone.
     */
    private static function target(ModerationAction $action): string
    {
        $id = $action->subject_id;

        return match ($action->subject_type) {
            'thread' => __('Thread #:id', ['id' => $id]),
            'post' => __('Post #:id', ['id' => $id]),
            'user' => __('Member #:id', ['id' => $id]),
            'report' => __('Report #:id', ['id' => $id]),
            default => "{$action->subject_type} #{$id}",
        };
    }
}
