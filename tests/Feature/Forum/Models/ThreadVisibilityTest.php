<?php

declare(strict_types=1);

use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Models\User;

beforeEach(function () {
    $this->author = User::factory()->create();

    $this->published = Thread::factory()->create(['title' => 'Published thread']);
    $this->pending = Thread::factory()->for($this->author, 'author')->pending()->create(['title' => 'Pending thread']);
    $this->hidden = Thread::factory()->for($this->author, 'author')->hidden()->create(['title' => 'Hidden thread']);
    Thread::factory()->create(['title' => 'Deleted thread'])->delete();
});

function visibleTitles(?User $viewer): array
{
    return Thread::query()->visibleTo($viewer)->orderBy('id')->pluck('title')->all();
}

it('shows guests only published threads', function () {
    expect(visibleTitles(null))->toBe(['Published thread']);
});

it('shows other members only published threads', function () {
    expect(visibleTitles(User::factory()->create()))->toBe(['Published thread']);
});

it('also shows authors their own threads awaiting review', function () {
    expect(visibleTitles($this->author))->toBe(['Published thread', 'Pending thread']);
});

it('shows staff every thread that is not deleted', function (string $role) {
    $staff = User::factory()->{$role}()->create();

    expect(visibleTitles($staff))->toBe(['Published thread', 'Pending thread', 'Hidden thread']);
})->with(['moderator', 'admin']);

it('casts the moderation status to an enum', function () {
    expect($this->pending->status)->toBe(ThreadStatus::PendingReview)
        ->and($this->published->status)->toBe(ThreadStatus::Published);
});
