<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

beforeEach(function () {
    $this->thread = Thread::factory()->create();
    $this->author = User::factory()->create();

    Post::factory()->for($this->thread)->create(['body' => 'Published reply']);
    Post::factory()->for($this->thread)->for($this->author, 'author')->pending()->create(['body' => 'Pending reply']);
    Post::factory()->for($this->thread)->for($this->author, 'author')->hidden()->create(['body' => 'Hidden reply']);
    Post::factory()->for($this->thread)->create(['body' => 'Deleted reply'])->delete();
});

function visibleReplies(Thread $thread, ?User $viewer): array
{
    return $thread->posts()->visibleTo($viewer)
        ->whereKeyNot($thread->first_post_id)
        ->orderBy('id')
        ->pluck('body')
        ->all();
}

it('shows guests and other members only published replies', function () {
    expect(visibleReplies($this->thread, null))->toBe(['Published reply'])
        ->and(visibleReplies($this->thread, User::factory()->create()))->toBe(['Published reply']);
});

it('also shows authors their own replies awaiting review', function () {
    expect(visibleReplies($this->thread, $this->author))->toBe(['Published reply', 'Pending reply']);
});

it('shows staff every reply that is not deleted', function () {
    expect(visibleReplies($this->thread, User::factory()->moderator()->create()))
        ->toBe(['Published reply', 'Pending reply', 'Hidden reply']);
});
