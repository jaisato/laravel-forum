<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Actions\Moderation\BanUser;
use App\Filament\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;

/**
 * Bans a member (BanUser), for good or until a date. Moderators ban members
 * and administrators anyone but administrators (UserPolicy::ban); banning
 * again replaces the reason and the end.
 */
final class BanUserAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'ban';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Ban'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('ban')
            ->modalDescription(__('A banned member can still read the forum but not take part, and loses their API tokens.'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
                DateTimePicker::make('until')
                    ->label(__('Until'))
                    ->helperText(__('Leave it empty to ban for good.'))
                    ->seconds(false)
                    ->minDate(fn (): CarbonInterface => now()),
            ])
            ->action(function (User $record, array $data, BanUser $banUser): void {
                /** @var array{reason: string, until?: string|null} $data */
                $banUser->handle(
                    $record,
                    Staff::current(),
                    $data['reason'],
                    filled($data['until'] ?? null) ? CarbonImmutable::parse($data['until']) : null,
                );

                $this->success();
            })
            ->successNotificationTitle(__('Member banned.'));
    }
}
