<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;

/**
 * What a client sends to start a thread in the category.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newThreadPayload(Category $category, array $overrides = []): array
{
    return [
        'category_id' => $category->id,
        'title' => 'Mis colas no arrancan en producción',
        'body' => 'Desde la última actualización Horizon no procesa nada.',
        ...$overrides,
    ];
}

describe('listing', function () {
    it('lists the public threads, a page at a time', function () {
        config(['forum.pagination.threads' => 2]);
        forumThread();
        forumThread();
        forumThread();

        $this->getJson('/api/v1/threads')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('links.next', url('/api/v1/threads?page=2'))
            ->assertJsonMissingPath('data.0.author.email');
    });

    it('narrows the listing to a category, a tag or a filter', function () {
        $laravel = Category::factory()->create(['slug' => 'laravel']);
        $horizon = Tag::factory()->create(['slug' => 'horizon']);
        $inCategory = forumThread(category: $laravel);
        $tagged = forumThread(tagIds: [$horizon->id]);
        forumReply(forumThread());

        $this->getJson('/api/v1/threads?category=laravel')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inCategory->id);

        $this->getJson('/api/v1/threads?tag=horizon')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tagged->id);

        $this->getJson('/api/v1/threads?filter=unanswered')->assertJsonCount(2, 'data');
    });

    it('keeps the listing in the page links', function () {
        config(['forum.pagination.threads' => 1]);
        $laravel = Category::factory()->create(['slug' => 'laravel']);
        forumThread(category: $laravel);
        forumThread(category: $laravel);

        $this->getJson('/api/v1/threads?category=laravel&sort=newest')
            ->assertJsonPath('links.next', url('/api/v1/threads?sort=newest&category=laravel&page=2'));
    });

    it('lists the threads members started or follow', function () {
        $member = User::factory()->create();
        $mine = forumThread(author: $member);
        forumThread();

        $this->withToken(apiToken($member))
            ->getJson('/api/v1/threads?filter=mine')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });

    it('refuses what it does not understand', function () {
        $this->getJson('/api/v1/threads?sort=popular&filter=nada&category=nada&tag=nada')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'filter', 'category', 'tag']);

        // Guests follow nothing and started nothing.
        $this->getJson('/api/v1/threads?filter=following')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    });

    it('never lists what the viewer may not see', function () {
        $moderator = User::factory()->moderator()->create();
        $public = forumThread();
        $hidden = forumThread();
        app(HidePost::class)->handle($hidden->firstPost, $moderator);

        $this->getJson('/api/v1/threads')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $public->id);

        $this->withToken(apiToken(User::factory()->create()))
            ->getJson('/api/v1/threads')
            ->assertJsonCount(1, 'data');

        $this->withToken(apiToken($moderator))
            ->getJson('/api/v1/threads')
            ->assertJsonCount(2, 'data');
    });
});

describe('showing', function () {
    it('tells a member whether it has something new for them, with its excerpt', function () {
        $member = User::factory()->create();
        $thread = forumThread(body: 'Cómo se ejecutan las colas en producción');
        $token = apiToken($member);
        // Reading its posts marks them read; then somebody replies.
        $this->withToken($token)->getJson("/api/v1/threads/{$thread->id}/posts")->assertOk();
        forumReply($thread);

        $this->withToken($token)->getJson("/api/v1/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.is_unread', true)
            ->assertJsonPath('data.excerpt', 'Cómo se ejecutan las colas en producción');
    });

    it('answers a missing thread as it answers one the client may not see', function () {
        config(['app.debug' => false]);
        $hidden = forumThread();
        app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());

        $missing = $this->getJson('/api/v1/threads/999999')->assertNotFound();
        $notShown = $this->getJson("/api/v1/threads/{$hidden->id}")->assertNotFound();

        // No model class, and nothing that tells a missing id from a hidden one.
        expect($missing->json())->toBe($notShown->json());
    });

    it('shows a thread and counts the visit', function () {
        $thread = forumThread();

        $this->getJson("/api/v1/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $thread->id)
            ->assertJsonPath('data.title', $thread->title)
            ->assertJsonPath('data.solution_post_id', null)
            ->assertJsonPath('meta.subscribed', false);

        expect($thread->fresh()->views_count)->toBe(1);
    });

    it('tells members whether they follow it', function () {
        $thread = forumThread();

        $this->withToken(apiToken($thread->author))
            ->getJson("/api/v1/threads/{$thread->id}")
            ->assertJsonPath('meta.subscribed', true);
    });

    it('answers 404 for threads the viewer may not see, and shows them to staff', function () {
        $moderator = User::factory()->moderator()->create();
        $hidden = forumThread();
        app(HidePost::class)->handle($hidden->firstPost, $moderator);
        $deleted = forumThread();
        app(DeleteThread::class)->handle($deleted, $moderator);

        foreach ([$hidden, $deleted] as $thread) {
            $this->withoutToken()->getJson("/api/v1/threads/{$thread->id}")->assertNotFound();

            $this->withToken(apiToken(User::factory()->create()))
                ->getJson("/api/v1/threads/{$thread->id}")
                ->assertNotFound();

            $this->withToken(apiToken($moderator))
                ->getJson("/api/v1/threads/{$thread->id}")
                ->assertOk();
        }
    });
});

describe('starting', function () {
    it('needs a token with the write ability', function () {
        $category = Category::factory()->create();

        $this->postJson('/api/v1/threads', newThreadPayload($category))->assertUnauthorized();

        $this->withToken(apiToken(User::factory()->create(), ['read']))
            ->postJson('/api/v1/threads', newThreadPayload($category))
            ->assertForbidden();

        expect(Thread::query()->count())->toBe(0);
    });

    it('starts a thread', function () {
        $member = User::factory()->create();
        $tag = Tag::factory()->create();

        $response = $this->withToken(apiToken($member))
            ->postJson('/api/v1/threads', newThreadPayload(Category::factory()->create(), ['tags' => [$tag->id]]))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mis colas no arrancan en producción')
            ->assertJsonPath('data.author.username', $member->username)
            ->assertJsonPath('data.tags.0.id', $tag->id);

        expect(Thread::query()->find($response->json('data.id'))->author->is($member))->toBeTrue();
    });

    it('validates the thread', function () {
        $this->withToken(apiToken(User::factory()->create()))
            ->postJson('/api/v1/threads', ['title' => 'Corto', 'tags' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'title', 'body', 'tags.0']);
    });

    it('keeps members out of locked categories', function () {
        $locked = Category::factory()->create(['is_locked' => true]);

        $this->withToken(apiToken(User::factory()->create()))
            ->postJson('/api/v1/threads', newThreadPayload($locked))
            ->assertForbidden();

        $this->withToken(apiToken(User::factory()->moderator()->create()))
            ->postJson('/api/v1/threads', newThreadPayload($locked))
            ->assertCreated();
    });
});

describe('editing', function () {
    it('changes only what is sent', function () {
        $tag = Tag::factory()->create();
        $thread = forumThread(tagIds: [$tag->id]);
        $token = apiToken($thread->author);

        $this->withToken($token)
            ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Un título mucho mejor para el hilo'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Un título mucho mejor para el hilo')
            ->assertJsonPath('data.tags.0.id', $tag->id);

        $this->withToken($token)
            ->patchJson("/api/v1/threads/{$thread->id}", ['tags' => []])
            ->assertOk()
            ->assertJsonPath('data.title', 'Un título mucho mejor para el hilo')
            ->assertJsonPath('data.tags', []);
    });

    it('lets only its author and staff edit it', function () {
        $thread = forumThread();

        $this->withToken(apiToken(User::factory()->create()))
            ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Un título que no le corresponde'])
            ->assertForbidden();

        $this->withToken(apiToken(User::factory()->moderator()->create()))
            ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Retitulado por moderación'])
            ->assertOk();
    });

    it('validates the title', function () {
        $thread = forumThread();

        $this->withToken(apiToken($thread->author))
            ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Corto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    });

    it('answers 404 to members for threads they may not see', function () {
        $thread = forumThread();
        app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create());

        $this->withToken(apiToken($thread->author))
            ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Un título mucho mejor para el hilo'])
            ->assertNotFound();
    });
});

describe('deleting', function () {
    it('deletes a thread nobody has answered yet', function () {
        $thread = forumThread();

        $this->withToken(apiToken($thread->author))
            ->deleteJson("/api/v1/threads/{$thread->id}")
            ->assertNoContent();

        expect(Thread::query()->find($thread->id))->toBeNull();
    });

    it('leaves threads with replies to staff', function () {
        $thread = forumThread();
        forumReply($thread);

        $this->withToken(apiToken($thread->author))
            ->deleteJson("/api/v1/threads/{$thread->id}")
            ->assertForbidden();

        $this->withToken(apiToken(User::factory()->moderator()->create()))
            ->deleteJson("/api/v1/threads/{$thread->id}")
            ->assertNoContent();
    });
});
