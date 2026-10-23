<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Actions\Moderation\ChangeUserRole;
use App\Enums\Role;
use App\Filament\Staff;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

/**
 * Makes a member a moderator or an administrator, or back (ChangeUserRole):
 * administrators only, and never on themselves (UserPolicy::changeRole).
 */
final class ChangeRoleAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'changeRole';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Change the role'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->authorize('changeRole')
            ->fillForm(fn (User $record): array => ['role' => $record->role->value])
            ->schema([
                Select::make('role')
                    ->label(__('Role'))
                    ->options(Role::class)
                    ->required(),
            ])
            ->action(function (User $record, array $data, ChangeUserRole $changeUserRole): void {
                $role = $data['role'] instanceof Role ? $data['role'] : Role::from((string) $data['role']);

                $changeUserRole->handle($record, Staff::current(), $role);

                $this->success();
            })
            ->successNotificationTitle(__('Role changed.'));
    }
}
