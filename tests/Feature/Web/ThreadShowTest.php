<?php

declare(strict_types=1);

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\ReplyToThread;
use App\Actions\Moderation\HidePost;
use App\Models\Thread;
use App\Models\ThreadRead;
use App\Models\User;
use App\Services\ReadTracker;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A thread with the given number of replies, one second apart.
 */
function threadWithReplies(int $replies, ?User $author = null): Thread
{
    $thread = forumThread($author);

    for ($i = 1; $i <= $replies; $i++) {
        test()->travel(1)->seconds();
        forumReply($thread, body: "Respuesta número {$i}.");
    }

    return $thread->fresh();
}

it('shows a public thread with its posts', function () {
    $thread = forumThread(body: 'El **cuerpo** del hilo.');
    $reply = forumReply($thread, body: 'Una respuesta.');

    $this->get(route('threads.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('threads/Show')
            ->where('thread.id', $thread->id)
            ->where('thread.title', $thread->title)
            ->where('thread.author.username', $thread->author->username)
            ->where('thread.solution_post_id', null)
            ->where('thread.is_deleted', false)
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $thread->first_post_id)
            ->where('posts.data.0.is_first', true)
            ->where('posts.data.0.body_html', fn (string $html) => str_contains($html, '<strong>cuerpo</strong>'))
            ->where('posts.data.1.id', $reply->id)
            ->where('posts.meta.total', 2)
            ->where('focus', null)
            ->where('subscribed', false)
            ->where('can.reply', false)
            ->missing('similar')
            ->missing('summary'));

    expect(route('threads.show', $thread))->toEndWith('/t/'.$thread->slug);
});

it('describes every post with what the viewer may do with it', function () {
    $thread = forumThread();
    $replier = User::factory()->create(['name' => 'Luis Pérez', 'username' => 'luis']);
    $reply = forumReply($thread, $replier, 'Prueba con `php artisan queue:work`.');
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->where('posts.data.1', [
            'id' => $reply->id,
            'thread_id' => $thread->id,
            'body_html' => $reply->body_html,
            // The viewer may reply, so they may quote it.
            'body' => 'Prueba con `php artisan queue:work`.',
            'status' => 'published',
            'is_first' => false,
            'is_solution' => false,
            'is_deleted' => false,
            'created_at' => $reply->created_at->toIso8601String(),
            'edited_at' => null,
            'author' => [
                'id' => $replier->id,
                'name' => 'Luis Pérez',
                'username' => 'luis',
                'avatar_url' => null,
                'role' => 'member',
            ],
            'reply_to' => null,
            'reactions' => [
                ['type' => 'like', 'emoji' => '👍', 'count' => 0, 'reacted' => false],
                ['type' => 'love', 'emoji' => '❤️', 'count' => 0, 'reacted' => false],
                ['type' => 'laugh', 'emoji' => '😂', 'count' => 0, 'reacted' => false],
                ['type' => 'insightful', 'emoji' => '💡', 'count' => 0, 'reacted' => false],
                ['type' => 'celebrate', 'emoji' => '🎉', 'count' => 0, 'reacted' => false],
            ],
            'can' => [
                'update' => false,
                'quote' => true,
                'delete' => false,
                'restore' => false,
                'react' => true,
                'report' => true,
                'hide' => false,
                'approve' => false,
                'mark_solution' => false,
            ],
        ]));
});

it('counts the reactions of every post and marks the viewer\'s own', function () {
    $thread = forumThread();
    $reply = forumReply($thread);
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->post(route('posts.reactions.toggle', $reply), ['type' => 'like']);
    $this->actingAs(User::factory()->create())->post(route('posts.reactions.toggle', $reply), ['type' => 'like']);

    $this->actingAs($viewer)->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->where('posts.data.1.reactions.0', ['type' => 'like', 'emoji' => '👍', 'count' => 2, 'reacted' => true])
        ->where('posts.data.1.reactions.1.count', 0));
});

it('shows who a reply answers', function () {
    $thread = forumThread();
    $quoted = forumReply($thread);
    $answer = app(ReplyToThread::class)->handle($thread->fresh(), User::factory()->create(), 'Cierto.', $quoted);

    $this->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->where('posts.data.2.id', $answer->id)
        ->where('posts.data.2.reply_to.id', $quoted->id)
        ->where('posts.data.2.reply_to.author.username', $quoted->author->username)
        // Guests may not quote: no Markdown source for them.
        ->missing('posts.data.2.body'));
});

it('paginates the posts', function () {
    config(['forum.pagination.posts' => 2]);
    $thread = threadWithReplies(3);

    $this->get(route('threads.show', [$thread, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('posts.data', 2)
        ->where('posts.meta.current_page', 2)
        ->where('posts.meta.last_page', 2)
        ->where('posts.meta.total', 4));
});

it('keeps hidden posts from members and shows staff everything, deleted posts included', function () {
    $thread = forumThread();
    $hidden = forumReply($thread);
    app(HidePost::class)->handle($hidden, User::factory()->moderator()->create());
    $deleted = forumReply($thread);
    app(DeletePost::class)->handle($deleted, $deleted->author);
    forumReply($thread);

    $this->actingAs(User::factory()->create())->get(route('threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 2));

    $this->actingAs(User::factory()->moderator()->create())->get(route('threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 4)
            ->where('posts.data.1.id', $hidden->id)
            ->where('posts.data.1.status', 'hidden')
            // Staff may edit a hidden post, but nobody may quote it.
            ->where('posts.data.1.can.update', true)
            ->where('posts.data.1.can.quote', false)
            ->has('posts.data.1.body')
            ->where('posts.data.2.id', $deleted->id)
            ->where('posts.data.2.is_deleted', true)
            ->where('posts.data.2.can.restore', true)
            // A deleted post is only restored: no edit form, no quote.
            ->where('posts.data.2.can.update', false)
            ->where('posts.data.2.can.quote', false)
            ->missing('posts.data.2.body'));
});

it('opens the page of a linked post and focuses it', function () {
    config(['forum.pagination.posts' => 2]);
    $thread = threadWithReplies(4);
    $target = $thread->posts()->orderByDesc('id')->first();

    // Notification links point at /t/{slug}?post={id}.
    $this->get(route('threads.show', [$thread, 'post' => $target->id]))->assertInertia(fn (Assert $page) => $page
        ->where('posts.meta.current_page', 3)
        ->where('focus', $target->id));
});

it('ignores a linked post the viewer may not see', function () {
    $thread = forumThread();
    $hidden = forumReply($thread);
    app(HidePost::class)->handle($hidden, User::factory()->moderator()->create());

    $this->get(route('threads.show', [$thread, 'post' => $hidden->id]))->assertInertia(fn (Assert $page) => $page
        ->where('posts.meta.current_page', 1)
        ->where('focus', null));
});

it('jumps to the first post the member has not read', function () {
    config(['forum.pagination.posts' => 2]);
    $member = User::factory()->create();
    $thread = threadWithReplies(1);
    app(ReadTracker::class)->markRead($thread, $member);
    $this->travel(1)->minutes();
    $firstUnread = forumReply($thread);
    forumReply($thread);

    $this->actingAs($member)->get(route('threads.show', [$thread, 'unread' => 1]))->assertInertia(fn (Assert $page) => $page
        ->where('posts.meta.current_page', 2)
        ->where('focus', $firstUnread->id));
});

it('marks as read the posts it shows', function () {
    config(['forum.pagination.posts' => 2]);
    $member = User::factory()->create();
    $thread = threadWithReplies(3);
    $shown = $thread->posts()->orderBy('id')->skip(1)->first();

    $this->actingAs($member)->get(route('threads.show', $thread));

    expect(ThreadRead::query()->where('thread_id', $thread->id)->where('user_id', $member->id)->value('last_read_post_id'))
        ->toBe($shown->id);
});

it('counts one view per visitor', function () {
    $thread = forumThread();
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('threads.show', $thread));
    $this->actingAs($member)->get(route('threads.show', $thread));

    expect($thread->fresh()->views_count)->toBe(1);
});

it('tells the viewer what they may do', function () {
    $thread = forumThread();
    $member = User::factory()->create();
    forumReply($thread, $member);

    $this->actingAs($member)->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->where('subscribed', true)
        ->where('can', [
            'reply' => true,
            'update' => false,
            'delete' => false,
            'restore' => false,
            'pin' => false,
            'lock' => false,
            'move' => false,
            'mark_solution' => false,
            'subscribe' => true,
        ])
        ->missing('moveTargets'));

    $this->actingAs(User::factory()->moderator()->create())->get(route('threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.pin', true)
            ->where('can.move', true)
            ->has('moveTargets', 1));
});

it('loads similar threads and the summary after the page', function () {
    $thread = forumThread();

    $this->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->missing('similar')
        ->missing('summary')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('similar', [])
            ->where('summary', null)));
});

it('loads the AI summary only while summaries are enabled', function () {
    $thread = forumThread();
    $thread->forceFill(['ai_summary' => 'Usa **Queue::fake()** en los tests.', 'ai_summary_generated_at' => now()])->save();

    config(['forum.ai.enabled' => true, 'forum.ai.summaries.enabled' => true]);

    $this->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('summary.html', fn (string $html) => str_contains($html, '<strong>Queue::fake()</strong>'))
            ->where('summary.generated_at', $thread->ai_summary_generated_at?->toIso8601String())));

    config(['forum.ai.summaries.enabled' => false]);

    $this->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('summary', null)));
});

it('gives the page its title and description', function () {
    $thread = forumThread(body: 'Mis **colas** no procesan los *jobs* retrasados desde ayer.');

    $this->get(route('threads.show', $thread))->assertInertia(fn (Assert $page) => $page
        ->where('meta.title', $thread->title)
        ->where('meta.description', 'Mis colas no procesan los jobs retrasados desde ayer.'));
});

it('answers 404 instead of revealing hidden threads', function (string $viewer) {
    $thread = Thread::factory()->hidden()->create();
    $user = match ($viewer) {
        'guest' => null,
        'member' => User::factory()->create(),
        // A moderator hid it: its author no longer sees it either.
        'author' => $thread->author,
    };

    ($user === null ? $this : $this->actingAs($user))
        ->get(route('threads.show', $thread))
        ->assertNotFound();
})->with(['guest', 'member', 'author']);

it('shows hidden threads to staff and pending threads to their author', function () {
    $hidden = Thread::factory()->hidden()->create();
    $pending = Thread::factory()->pending()->create();

    $this->actingAs(User::factory()->moderator()->create())
        ->get(route('threads.show', $hidden))
        ->assertOk();

    $this->actingAs($pending->author)
        ->get(route('threads.show', $pending))
        ->assertOk();
});

it('lets staff open a deleted thread to restore it', function () {
    $thread = forumThread();
    app(DeleteThread::class)->handle($thread, $thread->author);

    $this->get(route('threads.show', $thread))->assertNotFound();

    $this->actingAs(User::factory()->moderator()->create())
        ->get(route('threads.show', $thread))
        ->assertInertia(fn (Assert $page) => $page
            ->where('thread.is_deleted', true)
            ->where('can.restore', true));
});
