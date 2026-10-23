<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;

it('creates every thread with its opening post', function () {
    $thread = Thread::factory()->create();

    expect($thread->firstPost)->toBeInstanceOf(Post::class)
        ->and($thread->firstPost->thread_id)->toBe($thread->id)
        ->and($thread->firstPost->user_id)->toBe($thread->user_id)
        ->and($thread->lastPost->is($thread->firstPost))->toBeTrue()
        ->and($thread->firstPost->isFirst())->toBeTrue()
        ->and($thread->last_posted_at)->not->toBeNull();
});

it('links threads with their category, author, posts and tags', function () {
    $category = Category::factory()->create();
    $author = User::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $thread = Thread::factory()->for($category)->for($author, 'author')->create();
    $thread->tags()->attach($tags);
    $reply = Post::factory()->for($thread)->create();

    expect($thread->category->is($category))->toBeTrue()
        ->and($thread->author->is($author))->toBeTrue()
        ->and($thread->posts->pluck('id')->all())->toBe([$thread->first_post_id, $reply->id])
        ->and($thread->tags->pluck('id')->sort()->values()->all())->toBe($tags->pluck('id')->sort()->values()->all())
        ->and($category->threads->pluck('id')->all())->toBe([$thread->id])
        ->and($author->threads->pluck('id')->all())->toBe([$thread->id])
        ->and($author->posts->pluck('id')->all())->toBe([$thread->first_post_id])
        ->and($reply->isFirst())->toBeFalse();
});

it('keeps the author of content written by an erased account', function () {
    $author = User::factory()->create();
    $thread = Thread::factory()->for($author, 'author')->create();

    $author->delete();

    expect($thread->fresh()->author)->not->toBeNull()
        ->and($thread->fresh()->firstPost->author->is($author))->toBeTrue();
});

it('knows when a thread has an accepted answer', function () {
    $thread = Thread::factory()->create();
    $answer = Post::factory()->for($thread)->create();

    expect($thread->isSolved())->toBeFalse();

    $thread->forceFill(['solution_post_id' => $answer->id])->save();

    expect($thread->refresh()->isSolved())->toBeTrue()
        ->and($thread->solution->is($answer))->toBeTrue();
});

it('links a reply to the post it quotes', function () {
    $thread = Thread::factory()->create();
    $reply = Post::factory()->for($thread)->create(['reply_to_id' => $thread->first_post_id]);

    expect($reply->replyTo->is($thread->firstPost))->toBeTrue();
});

it('orders categories by position and then by name', function () {
    Category::factory()->create(['name' => 'Beta', 'position' => 2]);
    Category::factory()->create(['name' => 'Gamma', 'position' => 1]);
    Category::factory()->create(['name' => 'Alpha', 'position' => 2]);

    expect(Category::query()->ordered()->pluck('name')->all())->toBe(['Gamma', 'Alpha', 'Beta']);
});

it('routes categories, tags and threads by slug', function () {
    expect((new Thread)->getRouteKeyName())->toBe('slug')
        ->and((new Category)->getRouteKeyName())->toBe('slug')
        ->and((new Tag)->getRouteKeyName())->toBe('slug');
});
