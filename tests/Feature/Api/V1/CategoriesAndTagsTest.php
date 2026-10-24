<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;

it('lists the categories in their order, with their latest public thread', function () {
    $laravel = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel', 'position' => 2]);
    Category::factory()->create(['name' => 'Anuncios', 'slug' => 'anuncios', 'position' => 1]);
    $public = forumThread(category: $laravel);
    $hidden = forumThread(category: $laravel);
    app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'anuncios')
        ->assertJsonPath('data.0.latest_thread', null)
        ->assertJsonPath('data.1.slug', 'laravel')
        ->assertJsonPath('data.1.latest_thread.id', $public->id);
});

it('shows a category by its slug', function () {
    $laravel = Category::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $thread = forumThread(category: $laravel);

    $this->getJson('/api/v1/categories/laravel')
        ->assertOk()
        ->assertJsonPath('data.name', 'Laravel')
        ->assertJsonPath('data.latest_thread.id', $thread->id);

    $this->getJson('/api/v1/categories/nada')->assertNotFound();
});

it('lists the tags by name and shows one by its slug', function () {
    Tag::factory()->create(['name' => 'vue', 'slug' => 'vue']);
    Tag::factory()->create(['name' => 'eloquent', 'slug' => 'eloquent']);

    $this->getJson('/api/v1/tags')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'eloquent')
        ->assertJsonPath('data.1.slug', 'vue');

    $this->getJson('/api/v1/tags/vue')
        ->assertOk()
        ->assertJsonPath('data.name', 'vue');

    $this->getJson('/api/v1/tags/nada')->assertNotFound();
});
