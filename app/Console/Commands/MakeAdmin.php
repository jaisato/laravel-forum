<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Moderation\RecordModerationAction;
use App\Enums\ModerationActionType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Promotes an existing account to administrator: how the first admin of a
 * fresh installation is made. The change is logged as done by the system.
 */
#[AsCommand(name: 'forum:make-admin', description: 'Give an existing user the administrator role')]
final class MakeAdmin extends Command
{
    protected $signature = 'forum:make-admin {email : Email address of an existing account}';

    public function handle(RecordModerationAction $log): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user with the email address [{$email}].");

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->components->info("{$user->email} already is an administrator.");

            return self::SUCCESS;
        }

        $from = $user->role;

        DB::transaction(function () use ($user, $from, $log): void {
            $user->forceFill(['role' => Role::Admin])->save();

            $log->handle(null, ModerationActionType::UserRoleChanged, $user, 'Promoted from the command line', [
                'from' => $from->value,
                'to' => Role::Admin->value,
            ]);
        });

        $this->components->info("{$user->email} is now an administrator.");

        return self::SUCCESS;
    }
}
