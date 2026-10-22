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
        $from = $target->role;

        if ($from === $role) {
            return;
        }

        DB::transaction(function () use ($target, $admin, $role, $from): void {
            $target->forceFill(['role' => $role])->save();

            $this->log->handle($admin, ModerationActionType::UserRoleChanged, $target, meta: [
                'from' => $from->value,
                'to' => $role->value,
            ]);
        });
    }
}
