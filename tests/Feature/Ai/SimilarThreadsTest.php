<?php

declare(strict_types=1);

use App\Actions\Moderation\HidePost;
use App\Models\User;
use App\Services\Ai\KeywordSimilarThreadFinder;
use App\Services\Ai\SimilarThreadFinder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Similar threads work with or without AI: without pgvector, or with the
 * feature off, they come from keyword search (spec §4.6). The tests run
 * with AI off, so on both databases.
 */

it('uses keyword search while the vector search does not apply', function () {
    expect(app(SimilarThreadFinder::class))->toBeInstanceOf(KeywordSimilarThreadFinder::class);

    config(['forum.ai.enabled' => true, 'forum.ai.similar.enabled' => true]);

    if (DB::connection()->getDriverName() !== 'pgsql') {
        expect(app(SimilarThreadFinder::class))->toBeInstanceOf(KeywordSimilarThreadFinder::class);
    }
});

it('lists related threads on the thread page, never the thread itself nor hidden ones', function () {
    $thread = forumThread(body: 'Mis colas no arrancan');
    $related = forumThread(body: 'Las colas de Laravel');
    $hidden = forumThread(body: 'Más colas');
    app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());

    $this->get("/t/{$thread->slug}")->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('similar', 1)
            ->where('similar.0.id', $related->id)
            ->where('similar.0.slug', $related->slug)));
});

it('tells a member which related threads have something new, with their excerpts', function () {
    $member = User::factory()->create(['created_at' => now()->subDay()]);
    $thread = forumThread();
    $related = forumThread(body: 'Las colas se quedan paradas');

    $this->withToken(apiToken($member))->getJson("/api/v1/threads/{$thread->id}/similar")
        ->assertOk()
        ->assertJsonPath('data.0.id', $related->id)
        ->assertJsonPath('data.0.is_unread', true)
        ->assertJsonPath('data.0.excerpt', 'Las colas se quedan paradas');
});

it('gives the related threads through the API', function () {
    $thread = forumThread();
    $related = forumThread();
    $hidden = forumThread();
    app(HidePost::class)->handle($hidden->firstPost, User::factory()->moderator()->create());

    $this->getJson("/api/v1/threads/{$thread->id}/similar")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $related->id)
        ->assertJsonMissingPath('data.0.author.email');

    $this->getJson("/api/v1/threads/{$hidden->id}/similar")->assertNotFound();
});
