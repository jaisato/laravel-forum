<?php

declare(strict_types=1);

namespace App\Actions\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeUserRole
{
    public function __construct(private readonly RecordModerationAction $log) {}

    public function handle(User $target, User $admin, Role $role): void
    {
        DB::transaction(function () use ($target, $admin, $role): void {
            // The role the user has now, read under lock: another admin may
            // have changed it since this one loaded the user.
            $stored = User::withTrashed()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $from = $stored->role;

            if ($from !== $role) {
                $stored->forceFill(['role' => $role])->save();

                $this->log->handle($admin, ModerationActionType::UserRoleChanged, $stored, meta: [
                    'from' => $from->value,
                    'to' => $role->value,
                ]);
            }

            $target->setRawAttributes($stored->getAttributes(), sync: true);
        });
    }
}
