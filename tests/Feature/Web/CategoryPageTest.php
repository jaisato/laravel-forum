<?php

declare(strict_types=1);

use App\Actions\Moderation\PinThread;
use App\Models\Category;
use App\Models\Thread;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the threads of the category with the pinned ones first', function () {
    $category = Category::factory()->create([
        'name' => 'Anuncios',
        'description' => 'Novedades del foro.',
        'color' => '#0ea5e9',
        'icon' => 'megaphone',
    ]);
    $pinned = $this->travelTo(now()->subWeek(), fn () => forumThread(category: $category));
    app(PinThread::class)->handle($pinned, User::factory()->moderator()->create());
    $recent = forumThread(category: $category);
    forumThread();

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('threads/Index')
            ->where('context', [
                'type' => 'category',
                'category' => [
                    'id' => $category->id,
                    'name' => 'Anuncios',
                    'slug' => $category->slug,
                    'description' => 'Novedades del foro.',
                    'color' => '#0ea5e9',
                    'icon' => 'megaphone',
                    'is_locked' => false,
                    'threads_count' => 2,
                    'posts_count' => 2,
                ],
            ])
            ->has('threads.data', 2)
            ->where('threads.data.0.id', $pinned->id)
            ->where('threads.data.0.is_pinned', true)
            ->where('threads.data.1.id', $recent->id));
});

it('filters and sorts inside the category', function () {
    $category = Category::factory()->create();
    $answered = forumThread(category: $category);
    forumReply($answered);
    $unanswered = forumThread(category: $category);
    forumThread();

    $this->get(route('categories.show', [$category, 'filter' => 'unanswered']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.filter', 'unanswered')
            ->has('threads.data', 1)
            ->where('threads.data.0.id', $unanswered->id));
});

it('does not leak hidden threads of the category', function () {
    $category = Category::factory()->create();
    Thread::factory()->hidden()->for($category)->create();

    $this->get(route('categories.show', $category))
        ->assertInertia(fn (Assert $page) => $page->has('threads.data', 0));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('categories.show', $category))
        ->assertInertia(fn (Assert $page) => $page->has('threads.data', 1));
});

it('answers 404 for an unknown category', function () {
    $this->get('/c/no-existe')->assertNotFound();
});
