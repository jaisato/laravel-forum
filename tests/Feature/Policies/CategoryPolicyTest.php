<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;

it('lets everyone browse categories and tags', function (string $role) {
    $user = forumUser($role);

    expect(allows($user, 'viewAny', Category::class))->toBeTrue()
        ->and(allows($user, 'view', Category::factory()->create()))->toBeTrue()
        ->and(allows($user, 'viewAny', Tag::class))->toBeTrue()
        ->and(allows($user, 'view', Tag::factory()->create()))->toBeTrue();
})->with(['guest', 'member', 'admin']);

it('lets only administrators manage categories and tags', function (string $model, string $role, bool $expected) {
    $user = forumUser($role);
    $record = $model::factory()->create();

    expect(allows($user, 'create', $model))->toBe($expected)
        ->and(allows($user, 'update', $record))->toBe($expected)
        ->and(allows($user, 'delete', $record))->toBe($expected);
})->with([Category::class, Tag::class])->with([
    ['guest', false],
    ['member', false],
    ['moderator', false],
    ['admin', true],
]);

it('refuses to delete a category that still holds threads', function () {
    $category = Category::factory()->create();
    Thread::factory()->for($category)->create()->delete();

    expect(allows(forumUser('admin'), 'delete', $category))->toBeFalse();
});
