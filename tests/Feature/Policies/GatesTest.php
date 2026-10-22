<?php

declare(strict_types=1);

it('opens the admin panel to staff who are not banned', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'access-admin'))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['banned moderator', false],
    ['moderator', true],
    ['admin', true],
]);

it('opens Horizon to administrators only', function (string $role, bool $expected) {
    expect(allows(forumUser($role), 'viewHorizon'))->toBe($expected);
})->with([
    ['guest', false],
    ['member', false],
    ['moderator', false],
    ['admin', true],
]);

it('publishes the API documentation to everyone', function (string $role) {
    expect(allows(forumUser($role), 'viewApiDocs'))->toBeTrue();
})->with(['guest', 'member']);
