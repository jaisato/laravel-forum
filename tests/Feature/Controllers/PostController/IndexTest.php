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

it('passes posts to the view', function () {

    $posts = Post::factory(3)->create();

    $posts->load('user');

    get(route('posts.index'))
        ->assertInertia(
            fn (AssertableInertia $inertia) => $inertia->hasResource('post', PostResource::make($posts->first()))
                ->hasPaginatedResource('posts', PostResource::collection($posts->reverse()))
        );
});
