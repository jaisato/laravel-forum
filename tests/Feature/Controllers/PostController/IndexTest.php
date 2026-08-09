<?php

use App\Http\Resources\PostResource;
use App\Models\Post;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\get;

it('should return correct component', function () {
    Post::factory(3)->create();

    get(route('posts.index'))
        ->assertComponent('Posts/Index');
});

it('does not send props the page never reads', function () {
    Post::factory(3)->create();

    get(route('posts.index'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->missing('post'));
});

it('passes posts to the view', function () {

    $posts = Post::factory(3)->create();

    $posts->load('user');

    get(route('posts.index'))
        ->assertInertia(
            fn (AssertableInertia $inertia) => $inertia
                ->hasPaginatedResource('posts', PostResource::collection($posts->reverse()))
        );
});
