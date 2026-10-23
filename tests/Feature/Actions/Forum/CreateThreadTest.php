<?php

declare(strict_types=1);

use App\Events\PostCreated;
use App\Events\ThreadCreated;
use App\Models\Category;
use App\Models\Tag;
use App\Models\ThreadRead;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creates the thread with its opening post', function () {
    $author = User::factory()->create();
    $category = Category::factory()->create();

    $thread = forumThread($author, $category, "Hola **mundo**\n\n<script>alert(1)</script>");
    $post = $thread->firstPost;

    expect($thread->slug)->toBe('a-thread-about-testing-the-forum')
        ->and($thread->category->is($category))->toBeTrue()
        ->and($thread->author->is($author))->toBeTrue()
        ->and($post->author->is($author))->toBeTrue()
        ->and($post->body)->toBe("Hola **mundo**\n\n<script>alert(1)</script>")
        ->and($post->body_html)->toContain('<strong>mundo</strong>')
        ->and($post->body_html)->not->toContain('<script>')
        ->and($thread->last_post_id)->toBe($post->id)
        ->and($thread->last_posted_at->equalTo($post->created_at))->toBeTrue()
        ->and($thread->replies_count)->toBe(0);
});

it('counts the new thread in its category, its author and its tags', function () {
    $author = User::factory()->create();
    $category = Category::factory()->create();
    [$php, $vue] = Tag::factory()->count(2)->create();

    forumThread($author, $category, tagIds: [$php->id, $vue->id, $php->id, 999_999]);

    expect($category->fresh())->threads_count->toBe(1)->posts_count->toBe(1)
        ->and($author->fresh())->threads_count->toBe(1)->posts_count->toBe(1)
        ->and($php->fresh()->threads_count)->toBe(1)
        ->and($vue->fresh()->threads_count)->toBe(1);
});

it('attaches only existing tags, once each', function () {
    $tag = Tag::factory()->create();

    $thread = forumThread(tagIds: [$tag->id, $tag->id, 999_999]);

    expect($thread->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});

it('subscribes the author and marks the thread as read for them', function () {
    $author = User::factory()->create();

    $thread = forumThread($author);

    expect($thread->subscribers()->pluck('users.id')->all())->toBe([$author->id])
        ->and(ThreadRead::query()->where('user_id', $author->id)->value('last_read_post_id'))->toBe($thread->first_post_id);
});

it('announces the thread and its opening post once committed', function () {
    Event::fake([ThreadCreated::class, PostCreated::class]);

    $thread = forumThread();

    Event::assertDispatched(ThreadCreated::class, fn (ThreadCreated $event): bool => $event->thread->is($thread));
    Event::assertDispatched(PostCreated::class, fn (PostCreated $event): bool => $event->post->is($thread->firstPost));
});
