<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\Thread;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the threads with the tag', function () {
    $tag = Tag::factory()->create(['name' => 'eloquent', 'color' => '#ef4444']);
    $older = $this->travelTo(now()->subHour(), fn () => forumThread(tagIds: [$tag->id]));
    $newer = forumThread(tagIds: [$tag->id]);
    forumThread();

    $this->get(route('tags.show', $tag))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('threads/Index')
            ->where('context', [
                'type' => 'tag',
                'tag' => [
                    'id' => $tag->id,
                    'name' => 'eloquent',
                    'slug' => $tag->slug,
                    'color' => '#ef4444',
                    'threads_count' => 2,
                ],
            ])
            ->has('threads.data', 2)
            ->where('threads.data.0.id', $newer->id)
            ->where('threads.data.1.id', $older->id));
});

it('does not leak hidden threads with the tag', function () {
    $tag = Tag::factory()->create();
    $thread = Thread::factory()->hidden()->create();
    $thread->tags()->attach($tag);

    $this->get(route('tags.show', $tag))
        ->assertInertia(fn (Assert $page) => $page->has('threads.data', 0));
});

it('answers 404 for an unknown tag', function () {
    $this->get('/tags/no-existe')->assertNotFound();
});
