<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\BanUserAction;
use App\Filament\Resources\Users\Actions\ChangeRoleAction;
use App\Filament\Resources\Users\Actions\UnbanUserAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('profile')
                ->label(__('Public profile'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (User $record): string => route('users.show', $record->username))
                ->openUrlInNewTab(),
            BanUserAction::make(),
            UnbanUserAction::make(),
            ChangeRoleAction::make(),
        ];
    }
}
