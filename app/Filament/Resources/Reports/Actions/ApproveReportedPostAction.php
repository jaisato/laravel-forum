<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Actions;

use App\Actions\Moderation\ApprovePost;
use App\Enums\PostStatus;
use App\Filament\Staff;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Publishes again the reported post (ApprovePost): the way out for a post
 * the AI held for review by mistake. The report itself stays open until it
 * is dismissed.
 */
final class ApproveReportedPostAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'approvePost';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Approve the post'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize(fn (Report $record): bool => $record->post !== null && Gate::allows('approve', $record->post))
            ->visible(fn (Report $record): bool => $record->post !== null
                && ! $record->post->trashed()
                && $record->post->status !== PostStatus::Published)
            ->requiresConfirmation()
            ->action(function (Report $record, ApprovePost $approvePost): void {
                if ($record->post !== null) {
                    $approvePost->handle($record->post, Staff::current());
                }

                $this->success();
            })
            ->successNotificationTitle(__('Post approved.'));
    }
}
