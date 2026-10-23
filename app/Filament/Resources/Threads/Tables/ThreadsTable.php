<?php

declare(strict_types=1);

namespace App\Filament\Resources\Threads\Tables;

use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\RestoreThread;
use App\Actions\Moderation\LockThread;
use App\Actions\Moderation\MoveThread;
use App\Actions\Moderation\PinThread;
use App\Enums\ThreadStatus;
use App\Filament\Staff;
use App\Models\Category;
use App\Models\Thread;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * The threads by latest activity. Each decision goes through the forum's
 * own action, as from the thread page, and is logged under the moderator.
 */
final class ThreadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_posted_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn (Thread $record): ?string => $record->category?->name)
                    ->limit(70)
                    ->tooltip(fn (Thread $record): string => $record->title)
                    ->searchable(),
                TextColumn::make('author.username')
                    ->label(__('Author'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (ThreadStatus $state): string => match ($state) {
                        ThreadStatus::Published => 'success',
                        ThreadStatus::PendingReview => 'warning',
                        ThreadStatus::Hidden => 'danger',
                    }),
                IconColumn::make('is_pinned')
                    ->label(__('Pinned'))
                    ->boolean(),
                IconColumn::make('is_locked')
                    ->label(__('Locked'))
                    ->boolean(),
                TextColumn::make('replies_count')
                    ->label(__('Replies'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_posted_at')
                    ->label(__('Last activity'))
                    ->since()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('Deleted'))
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('Category'))
                    ->relationship('category', 'name'),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(ThreadStatus::class),
                TernaryFilter::make('is_pinned')
                    ->label(__('Pinned')),
                TernaryFilter::make('is_locked')
                    ->label(__('Locked')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewInForum')
                        ->label(__('View in the forum'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Thread $record): string => $record->url())
                        ->openUrlInNewTab(),
                    self::pin(),
                    self::unpin(),
                    self::lock(),
                    self::unlock(),
                    self::move(),
                    self::delete(),
                    self::restore(),
                ]),
            ]);
    }

    private static function pin(): Action
    {
        return Action::make('pin')
            ->label(__('Pin'))
            ->icon(Heroicon::OutlinedMapPin)
            ->authorize('pin')
            ->visible(fn (Thread $record): bool => ! $record->is_pinned && ! $record->trashed())
            ->action(function (Thread $record, PinThread $pinThread, Action $action): void {
                $pinThread->handle($record, Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Thread pinned.'));
    }

    private static function unpin(): Action
    {
        return Action::make('unpin')
            ->label(__('Unpin'))
            ->icon(Heroicon::OutlinedMapPin)
            ->authorize('pin')
            ->visible(fn (Thread $record): bool => $record->is_pinned && ! $record->trashed())
            ->action(function (Thread $record, PinThread $pinThread, Action $action): void {
                $pinThread->handle($record, Staff::current(), pinned: false);
                $action->success();
            })
            ->successNotificationTitle(__('Thread unpinned.'));
    }

    private static function lock(): Action
    {
        return Action::make('lock')
            ->label(__('Lock'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->authorize('lock')
            ->visible(fn (Thread $record): bool => ! $record->is_locked && ! $record->trashed())
            ->action(function (Thread $record, LockThread $lockThread, Action $action): void {
                $lockThread->handle($record, Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Thread locked.'));
    }

    private static function unlock(): Action
    {
        return Action::make('unlock')
            ->label(__('Unlock'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->authorize('lock')
            ->visible(fn (Thread $record): bool => $record->is_locked && ! $record->trashed())
            ->action(function (Thread $record, LockThread $lockThread, Action $action): void {
                $lockThread->handle($record, Staff::current(), locked: false);
                $action->success();
            })
            ->successNotificationTitle(__('Thread unlocked.'));
    }

    private static function move(): Action
    {
        return Action::make('move')
            ->label(__('Move'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->authorize('move')
            ->visible(fn (Thread $record): bool => ! $record->trashed())
            ->schema([
                Select::make('category_id')
                    ->label(__('Category'))
                    // Another category: moving a thread where it is does nothing.
                    ->options(fn (Thread $record): array => Category::query()
                        ->ordered()
                        ->whereKeyNot($record->category_id)
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(function (Thread $record, array $data, MoveThread $moveThread, Action $action): void {
                $moveThread->handle($record, Category::query()->findOrFail((int) $data['category_id']), Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Thread moved.'));
    }

    private static function delete(): Action
    {
        return Action::make('delete')
            ->label(__('Delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize('delete')
            ->visible(fn (Thread $record): bool => ! $record->trashed())
            ->modalDescription(__('The thread and its replies stop showing in the forum. It can be restored.'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Thread $record, array $data, DeleteThread $deleteThread, Action $action): void {
                $deleteThread->handle($record, Staff::current(), filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                $action->success();
            })
            ->successNotificationTitle(__('Thread deleted.'));
    }

    private static function restore(): Action
    {
        return Action::make('restore')
            ->label(__('Restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->authorize('restore')
            ->visible(fn (Thread $record): bool => $record->trashed())
            ->requiresConfirmation()
            ->action(function (Thread $record, RestoreThread $restoreThread, Action $action): void {
                $restoreThread->handle($record, Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Thread restored.'));
    }
}
