<?php

declare(strict_types=1);

namespace App\Filament\Resources\Posts\Tables;

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\RestorePost;
use App\Actions\Moderation\ApprovePost;
use App\Actions\Moderation\HidePost;
use App\Enums\PostStatus;
use App\Filament\Staff;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * The posts, newest first, with their open reports. Each decision goes
 * through the forum's own action, as from the thread page, and is logged
 * under the moderator.
 */
final class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label(__('Post'))
                    ->description(fn (Post $record): ?string => $record->thread?->title)
                    ->limit(90)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('author.username')
                    ->label(__('Author'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (PostStatus $state): string => match ($state) {
                        PostStatus::Published => 'success',
                        PostStatus::PendingReview => 'warning',
                        PostStatus::Hidden => 'danger',
                    }),
                TextColumn::make('open_reports_count')
                    ->label(__('Open reports'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Written'))
                    ->since()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('Deleted'))
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PostStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewInForum')
                        ->label(__('View in the forum'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Post $record): string => $record->url())
                        ->openUrlInNewTab(),
                    self::approve(),
                    self::hide(),
                    self::delete(),
                    self::restore(),
                ]),
            ]);
    }

    private static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('approve')
            ->visible(fn (Post $record): bool => $record->status !== PostStatus::Published && ! $record->trashed())
            ->requiresConfirmation()
            ->action(function (Post $record, ApprovePost $approvePost, Action $action): void {
                $approvePost->handle($record, Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Post approved.'));
    }

    private static function hide(): Action
    {
        return Action::make('hide')
            ->label(__('Hide'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('warning')
            ->authorize('hide')
            ->visible(fn (Post $record): bool => $record->status !== PostStatus::Hidden && ! $record->trashed())
            ->modalDescription(__('Only the staff will see it. Its author is told.'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Post $record, array $data, HidePost $hidePost, Action $action): void {
                $hidePost->handle($record, Staff::current(), filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                $action->success();
            })
            ->successNotificationTitle(__('Post hidden.'));
    }

    private static function delete(): Action
    {
        return Action::make('delete')
            ->label(__('Delete'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize('delete')
            ->visible(fn (Post $record): bool => ! $record->trashed())
            ->modalDescription(__('Deleting the opening post deletes the whole thread. It can be restored.'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (Post $record, array $data, DeletePost $deletePost, Action $action): void {
                $deletePost->handle($record, Staff::current(), filled($data['reason'] ?? null) ? (string) $data['reason'] : null);
                $action->success();
            })
            ->successNotificationTitle(__('Post deleted.'));
    }

    private static function restore(): Action
    {
        return Action::make('restore')
            ->label(__('Restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->authorize('restore')
            ->visible(fn (Post $record): bool => $record->trashed())
            ->requiresConfirmation()
            ->action(function (Post $record, RestorePost $restorePost, Action $action): void {
                $restorePost->handle($record, Staff::current());
                $action->success();
            })
            ->successNotificationTitle(__('Post restored.'));
    }
}
