<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Moderation\HidePost;
use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

/**
 * The addresses the sitemap lists, with their last modification.
 *
 * @return array<string, string|null>
 */
function sitemap(TestCase $test): array
{
    $response = $test->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse();

    $urls = [];

    foreach ($xml->url as $url) {
        $urls[(string) $url->loc] = isset($url->lastmod) ? (string) $url->lastmod : null;
    }

    return $urls;
}

it('lists the home page, the categories and the public threads', function () {
    $category = Category::factory()->create(['slug' => 'laravel']);
    $thread = forumThread(category: $category);

    expect(sitemap($this))->toBe([
        url('/') => null,
        url('/c/laravel') => null,
        url("/t/{$thread->slug}") => $thread->last_posted_at->toAtomString(),
    ]);
});

it('leaves out every thread the public may not see', function () {
    $moderator = User::factory()->moderator()->create();
    $public = forumThread();
    $held = forumThread();
    $hidden = forumThread();
    $deleted = forumThread();
    app(HidePost::class)->handle($held->firstPost, null);
    app(HidePost::class)->handle($hidden->firstPost, $moderator);
    app(DeleteThread::class)->handle($deleted, $moderator);

    $threads = array_values(array_filter(array_keys(sitemap($this)), fn (string $url): bool => str_contains($url, '/t/')));

    expect($threads)->toBe([url("/t/{$public->slug}")]);
});

it('lists the most recently active threads first, up to its limit', function () {
    config(['forum.sitemap.threads' => 2]);
    $old = forumThread();
    $this->travel(1)->minutes();
    $newer = forumThread();
    $this->travel(1)->minutes();
    $newest = forumThread();

    $threads = array_values(array_filter(array_keys(sitemap($this)), fn (string $url): bool => str_contains($url, '/t/')));

    expect($threads)->toBe([url("/t/{$newest->slug}"), url("/t/{$newer->slug}")])
        ->and($threads)->not->toContain(url("/t/{$old->slug}"));
});

it('is rebuilt every hour', function () {
    forumThread();
    sitemap($this);
    $later = forumThread();

    expect(sitemap($this))->not->toHaveKey(url("/t/{$later->slug}"));

    $this->travel(61)->minutes();

    expect(sitemap($this))->toHaveKey(url("/t/{$later->slug}"));
});

it('stops listing a thread the moment it leaves public view, built list or not', function (Closure $retract) {
    $thread = forumThread();
    expect(sitemap($this))->toHaveKey(url("/t/{$thread->slug}"));

    $retract($thread);

    expect(sitemap($this))->not->toHaveKey(url("/t/{$thread->slug}"));
})->with([
    'hidden by a moderator' => [fn ($thread) => app(HidePost::class)->handle($thread->firstPost, User::factory()->moderator()->create())],
    'held by the AI' => [fn ($thread) => app(HidePost::class)->handle($thread->firstPost, null)],
    'deleted by its author' => [fn ($thread) => app(DeleteThread::class)->handle($thread, $thread->author)],
]);
