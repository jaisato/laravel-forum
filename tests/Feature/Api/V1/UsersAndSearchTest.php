<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Actions\Users\DeleteAccount;
use App\Models\Category;
use App\Models\User;

describe('profiles', function () {
    it('shows public profiles, never the email', function () {
        $ana = User::factory()->create(['username' => 'ana', 'bio' => 'Desarrolladora PHP.']);

        $this->getJson('/api/v1/users/ana')
            ->assertOk()
            ->assertJsonPath('data.username', 'ana')
            ->assertJsonPath('data.bio', 'Desarrolladora PHP.')
            ->assertJsonMissingPath('data.email');

        // Not even to its owner: their email lives at /me.
        $this->withToken(apiToken($ana))
            ->getJson('/api/v1/users/ana')
            ->assertJsonMissingPath('data.email');
    });

    it('does not find erased accounts, by their old username or their new one', function () {
        $ana = User::factory()->create(['username' => 'ana']);
        app(DeleteAccount::class)->handle($ana);
        $erased = User::withTrashed()->findOrFail($ana->id);

        $this->getJson('/api/v1/users/ana')->assertNotFound();
        $this->getJson("/api/v1/users/{$erased->username}")->assertNotFound();
        $this->getJson("/api/v1/users/{$erased->username}/threads")->assertNotFound();
    });

    it('lists the threads a member started and their replies, as the viewer may see them', function () {
        $moderator = User::factory()->moderator()->create();
        $ana = User::factory()->create(['username' => 'ana']);
        $thread = forumThread(author: $ana);
        $hiddenThread = forumThread(author: $ana);
        app(HidePost::class)->handle($hiddenThread->firstPost, $moderator);
        forumThread();
        $reply = forumReply(forumThread(), author: $ana);
        app(HidePost::class)->handle(forumReply(forumThread(), author: $ana), $moderator);

        $this->getJson('/api/v1/users/ana/threads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $thread->id);

        $this->getJson('/api/v1/users/ana/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reply->id)
            ->assertJsonPath('data.0.thread.id', $reply->thread_id);

        $this->withToken(apiToken($moderator))
            ->getJson('/api/v1/users/ana/threads')
            ->assertJsonCount(2, 'data');
    });
});

describe('search', function () {
    it('finds threads and posts', function () {
        $thread = forumThread(body: 'Mis colas no arrancan');
        forumThread(body: 'Otra cosa distinta');
        $reply = forumReply(forumThread(), body: 'La migración falla en PostgreSQL');

        $this->getJson('/api/v1/search/threads?q=colas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $thread->id);

        $this->getJson('/api/v1/search/posts?q=migración')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reply->id)
            ->assertJsonPath('data.0.excerpt', 'La migración falla en PostgreSQL');
    });

    it('tells a member which threads found have something new for them', function () {
        $member = User::factory()->create(['created_at' => now()->subDay()]);
        $thread = forumThread(body: 'Mis colas no arrancan');

        $this->withToken(apiToken($member))->getJson('/api/v1/search/threads?q=colas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $thread->id)
            ->assertJsonPath('data.0.is_unread', true)
            ->assertJsonPath('data.0.excerpt', 'Mis colas no arrancan');
    });

    it('narrows the search to a category or a tag', function () {
        $laravel = Category::factory()->create(['slug' => 'laravel']);
        $inCategory = forumThread(category: $laravel, body: 'Mis colas no arrancan');
        forumThread(body: 'Mis colas no arrancan');

        $this->getJson('/api/v1/search/threads?q=colas&category=laravel')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inCategory->id);
    });

    it('needs something to look for, and knows the categories and tags it narrows to', function () {
        $this->getJson('/api/v1/search/threads')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->getJson('/api/v1/search/posts?q='.str_repeat('a', 201).'&category=nada&tag=nada')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'category', 'tag']);
    });

    it('keeps the search in the page links', function () {
        config(['forum.pagination.search' => 1]);
        forumThread(body: 'Mis colas no arrancan');
        forumThread(body: 'Mis colas no arrancan');

        $this->getJson('/api/v1/search/threads?q=colas')
            ->assertJsonPath('links.next', url('/api/v1/search/threads?q=colas&page=2'));
    });

    it('never finds what the public may not see, whoever searches', function (string $role) {
        $moderator = User::factory()->moderator()->create();
        $public = forumThread(body: 'Palabra secreta');
        $hidden = forumThread(body: 'Palabra secreta');
        $deleted = forumThread(body: 'Palabra secreta');
        app(HidePost::class)->handle($hidden->firstPost, $moderator);
        app(DeleteThread::class)->handle($deleted, $moderator);

        $viewer = forumUser($role);

        if ($viewer !== null) {
            $this->withToken(apiToken($viewer));
        }

        $this->getJson('/api/v1/search/threads?q=secreta')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id);
    })->with(['guest', 'member', 'moderator']);
});
