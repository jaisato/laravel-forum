<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Laravel\Jetstream\Features;

test('user accounts can be deleted', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->delete('/user', [
        'password' => 'password',
    ]);

    expect($user->fresh())->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');

test('correct password must be provided before account can be deleted', function () {
    $this->actingAs($user = User::factory()->create());

    $response = $this->delete('/user', [
        'password' => 'wrong-password',
    ]);

    expect($user->fresh())->not->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');

test('user accounts with posts and comments can be deleted', function () {
    $this->actingAs($user = User::factory()->create());

    $post = Post::factory()->for($user)->create();
    $ownComment = Comment::factory()->for($user)->for(Post::factory())->create();
    $otherComment = Comment::factory()->for($post)->create();

    $this->delete('/user', [
        'password' => 'password',
    ]);

    expect($user->fresh())->toBeNull()
        ->and($post->fresh())->toBeNull()
        ->and($ownComment->fresh())->toBeNull()
        ->and($otherComment->fresh())->toBeNull();
})->skip(function () {
    return ! Features::hasAccountDeletionFeatures();
}, 'Account deletion is not enabled.');
