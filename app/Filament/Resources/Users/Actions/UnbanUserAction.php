<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Actions\Moderation\UnbanUser;
use App\Filament\Staff;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Lifts a ban (UnbanUser), or clears one that already lapsed. Whoever may
 * ban the member may lift it (UserPolicy::ban).
 */
final class UnbanUserAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'unban';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Lift the ban'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->authorize('ban')
            ->visible(fn (User $record): bool => $record->banned_at !== null)
            ->requiresConfirmation()
            ->action(function (User $record, UnbanUser $unbanUser): void {
                $unbanUser->handle($record, Staff::current());

                $this->success();
            })
            ->successNotificationTitle(__('Ban lifted.'));
    }
}
