<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Actions\Moderation\LockThread;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;

describe('reading', function () {
    it('lists the posts of a thread, oldest first, a page at a time', function () {
        config(['forum.pagination.posts' => 2]);
        $thread = forumThread();
        $reply = forumReply($thread, body: 'Prueba a **reiniciar** Horizon.');
        forumReply($thread);

        $this->getJson("/api/v1/threads/{$thread->id}/posts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $thread->first_post_id)
            ->assertJsonPath('data.0.is_first', true)
            ->assertJsonPath('data.1.id', $reply->id)
            ->assertJsonPath('data.1.body_html', '<p>Prueba a <strong>reiniciar</strong> Horizon.</p>')
            ->assertJsonPath('data.1.can.update', false)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('links.next', url("/api/v1/threads/{$thread->id}/posts?page=2"))
            ->assertJsonMissingPath('data.1.author.email');
    });

    it('shows staff the posts others may not see', function () {
        $moderator = User::factory()->moderator()->create();
        $thread = forumThread();
        app(HidePost::class)->handle(forumReply($thread), $moderator);

        $this->getJson("/api/v1/threads/{$thread->id}/posts")->assertJsonCount(1, 'data');

        $this->withToken(apiToken(User::factory()->create()))
            ->getJson("/api/v1/threads/{$thread->id}/posts")
            ->assertJsonCount(1, 'data');

        $this->withToken(apiToken($moderator))
            ->getJson("/api/v1/threads/{$thread->id}/posts")
            ->assertJsonCount(2, 'data');
    });

    it('marks what members read as read', function () {
        $member = User::factory()->create(['created_at' => now()->subDay()]);
        $thread = forumThread();
        forumReply($thread);
        $token = apiToken($member);

        $this->withToken($token)->getJson('/api/v1/threads')->assertJsonPath('data.0.is_unread', true);
        $this->withToken($token)->getJson("/api/v1/threads/{$thread->id}/posts")->assertOk();
        $this->withToken($token)->getJson('/api/v1/threads')->assertJsonPath('data.0.is_unread', false);
    });

    it('answers 404 for the posts of threads the viewer may not see', function () {
        $thread = forumThread();
        app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

        $this->getJson("/api/v1/threads/{$thread->id}/posts")->assertNotFound();

        $this->withToken(apiToken(User::factory()->create()))
            ->getJson("/api/v1/threads/{$thread->id}/posts")
            ->assertNotFound();
    });

    it('shows a post, unless the viewer may not see it', function () {
        $moderator = User::factory()->moderator()->create();
        $thread = forumThread();
        $reply = forumReply($thread);

        $this->getJson("/api/v1/posts/{$reply->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $reply->id)
            ->assertJsonPath('data.thread_id', $thread->id);

        app(HidePost::class)->handle($reply, $moderator);

        $this->getJson("/api/v1/posts/{$reply->id}")->assertNotFound();

        $this->withToken(apiToken($reply->author))
            ->getJson("/api/v1/posts/{$reply->id}")
            ->assertNotFound();

        $this->withToken(apiToken($moderator))
            ->getJson("/api/v1/posts/{$reply->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden');
    });
});

describe('replying', function () {
    it('needs a token with the write ability', function () {
        $thread = forumThread();

        $this->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'Prueba a reiniciar Horizon.'])
            ->assertUnauthorized();

        $this->withToken(apiToken(User::factory()->create(), ['read']))
            ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'Prueba a reiniciar Horizon.'])
            ->assertForbidden();

        expect($thread->posts()->count())->toBe(1);
    });

    it('replies, quoting a post', function () {
        $thread = forumThread();
        $member = User::factory()->create();

        $this->withToken(apiToken($member))
            ->postJson("/api/v1/threads/{$thread->id}/posts", [
                'body' => 'Prueba a reiniciar Horizon.',
                'reply_to_id' => $thread->first_post_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.author.username', $member->username)
            ->assertJsonPath('data.body', 'Prueba a reiniciar Horizon.')
            ->assertJsonPath('data.reply_to.id', $thread->first_post_id)
            ->assertJsonPath('data.can.update', true);

        expect($thread->fresh()->replies_count)->toBe(1);
    });

    it('validates the reply', function () {
        $thread = forumThread();
        $elsewhere = forumThread();

        $this->withToken(apiToken(User::factory()->create()))
            ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => '', 'reply_to_id' => $elsewhere->first_post_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body', 'reply_to_id']);
    });

    it('leaves locked threads to staff', function () {
        $moderator = User::factory()->moderator()->create();
        $thread = forumThread();
        app(LockThread::class)->handle($thread, $moderator);

        $this->withToken(apiToken(User::factory()->create()))
            ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'Prueba a reiniciar Horizon.'])
            ->assertForbidden();

        $this->withToken(apiToken($moderator))
            ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'Cerrado por moderación.'])
            ->assertCreated();
    });

    it('answers 404 to members for threads they may not see', function () {
        $thread = forumThread();
        app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

        $this->withToken(apiToken(User::factory()->create()))
            ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'Prueba a reiniciar Horizon.'])
            ->assertNotFound();
    });
});

describe('editing and deleting', function () {
    it('lets authors edit their posts', function () {
        $reply = forumReply(forumThread());

        $this->withToken(apiToken($reply->author))
            ->patchJson("/api/v1/posts/{$reply->id}", ['body' => 'Corregido: reinicia Horizon y Redis.'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Corregido: reinicia Horizon y Redis.');

        expect($reply->fresh()->body)->toBe('Corregido: reinicia Horizon y Redis.');
    });

    it('lets nobody else edit them', function () {
        $reply = forumReply(forumThread());

        $this->withToken(apiToken(User::factory()->create()))
            ->patchJson("/api/v1/posts/{$reply->id}", ['body' => 'No es mío.'])
            ->assertForbidden();
    });

    it('validates the post', function () {
        $reply = forumReply(forumThread());

        $this->withToken(apiToken($reply->author))
            ->patchJson("/api/v1/posts/{$reply->id}", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    });

    it('answers 404 to members for posts they may not see', function () {
        $reply = forumReply(forumThread());
        app(HidePost::class)->handle($reply, User::factory()->moderator()->create());

        $this->withToken(apiToken($reply->author))
            ->patchJson("/api/v1/posts/{$reply->id}", ['body' => 'Lo arreglo.'])
            ->assertNotFound();

        $this->withToken(apiToken($reply->author))
            ->deleteJson("/api/v1/posts/{$reply->id}")
            ->assertNotFound();
    });

    it('deletes a reply', function () {
        $reply = forumReply(forumThread());

        $this->withToken(apiToken($reply->author))
            ->deleteJson("/api/v1/posts/{$reply->id}")
            ->assertNoContent();

        expect(Post::query()->find($reply->id))->toBeNull();
    });

    it('deletes the thread with its opening post', function () {
        $thread = forumThread();

        $this->withToken(apiToken($thread->author))
            ->deleteJson("/api/v1/posts/{$thread->first_post_id}")
            ->assertNoContent();

        expect(Thread::query()->find($thread->id))->toBeNull();
    });
});
