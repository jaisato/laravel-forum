<?php

declare(strict_types=1);

use App\Enums\Role;

it('treats administrators and moderators as staff', function (Role $role, bool $isStaff) {
    expect($role->isStaff())->toBe($isStaff);
})->with([
    'admin' => [Role::Admin, true],
    'moderator' => [Role::Moderator, true],
    'member' => [Role::Member, false],
]);

it('has a human readable label for every role', function () {
    expect(Role::Admin->label())->toBe('Administrator')
        ->and(Role::Moderator->label())->toBe('Moderator')
        ->and(Role::Member->label())->toBe('Member');
});

it('translates the role labels', function () {
    app()->setLocale('es');

    expect(Role::Admin->label())->toBe('Administrador')
        ->and(Role::Moderator->label())->toBe('Moderador')
        ->and(Role::Member->label())->toBe('Miembro');
});
