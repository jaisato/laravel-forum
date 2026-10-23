<?php

declare(strict_types=1);

use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\MarkSolution;
use App\Actions\Moderation\PinThread;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Queries\ThreadListing;
use App\Services\ThreadSubscriptions;

/**
 * The ids of the first page of a listing, in order.
 *
 * @param  array<string, string>  $filters
 * @return list<int>
 */
function listingIds(array $filters = [], ?User $viewer = null): array
{
    return array_map(
        fn (Thread $thread): int => $thread->id,
        app(ThreadListing::class)->paginate($filters, $viewer)->items(),
    );
}

it('lists threads by latest activity by default', function () {
    $quiet = $this->travelTo(now()->subDays(2), fn () => forumThread());
    $revived = $this->travelTo(now()->subDays(3), fn () => forumThread());
    $this->travelTo(now()->subHour(), fn () => forumReply($revived));
    $recent = $this->travelTo(now()->subDay(), fn () => forumThread());

    expect(listingIds())->toBe([$revived->id, $recent->id, $quiet->id])
        ->and(listingIds(['sort' => 'activity']))->toBe([$revived->id, $recent->id, $quiet->id]);
});

it('breaks ties by the most recent thread so pages never shuffle', function () {
    $this->freezeTime();
    $first = forumThread();
    $second = forumThread();

    expect(listingIds())->toBe([$second->id, $first->id])
        ->and(listingIds(['sort' => 'replies']))->toBe([$second->id, $first->id]);
});

it('sorts the newest threads first', function () {
    $older = $this->travelTo(now()->subDays(2), fn () => forumThread());
    $newer = $this->travelTo(now()->subDay(), fn () => forumThread());
    $this->travelTo(now()->subMinute(), fn () => forumReply($older));

    expect(listingIds(['sort' => 'newest']))->toBe([$newer->id, $older->id]);
});

it('sorts the most replied threads first', function () {
    $one = forumThread();
    forumReply($one);
    $three = forumThread();
    forumReply($three);
    forumReply($three);
    forumReply($three);
    $none = forumThread();

    expect(listingIds(['sort' => 'replies']))->toBe([$three->id, $one->id, $none->id]);
});

it('sorts the most viewed threads first', function () {
    [$few, $many, $none] = [forumThread(), forumThread(), forumThread()];
    Thread::query()->whereKey($few->id)->update(['views_count' => 4]);
    Thread::query()->whereKey($many->id)->update(['views_count' => 90]);

    expect(listingIds(['sort' => 'views']))->toBe([$many->id, $few->id, $none->id]);
});

it('filters the threads nobody answered yet', function () {
    $answered = forumThread();
    forumReply($answered);
    $unanswered = forumThread();

    expect(listingIds(['filter' => 'unanswered']))->toBe([$unanswered->id]);
});

it('filters solved and unsolved threads', function () {
    $solved = forumThread();
    app(MarkSolution::class)->handle($solved, forumReply($solved), $solved->author);
    $unsolved = forumThread();

    expect(listingIds(['filter' => 'solved']))->toBe([$solved->id])
        ->and(listingIds(['filter' => 'unsolved']))->toBe([$unsolved->id]);
});

it('filters the threads the viewer follows', function () {
    $viewer = User::factory()->create();
    $followed = forumThread();
    app(ThreadSubscriptions::class)->subscribe($followed, $viewer);
    $answered = forumThread();
    forumReply($answered, $viewer);
    forumThread();

    expect(listingIds(['filter' => 'following'], $viewer))->toBe([$answered->id, $followed->id]);
});

it('filters the threads the viewer started', function () {
    $viewer = User::factory()->create();
    $own = forumThread($viewer);
    forumReply(forumThread(), $viewer);

    expect(listingIds(['filter' => 'mine'], $viewer))->toBe([$own->id]);
});

it('ignores the personal filters for guests', function (string $filter) {
    $thread = forumThread();

    expect(listingIds(['filter' => $filter]))->toBe([$thread->id]);
})->with(['following', 'mine']);

it('narrows the listing to a category with its pinned threads first', function () {
    $category = Category::factory()->create();
    $pinned = $this->travelTo(now()->subWeek(), fn () => forumThread(category: $category));
    app(PinThread::class)->handle($pinned, User::factory()->moderator()->create());
    $recent = forumThread(category: $category);
    $elsewhere = forumThread();

    expect(listingIds(['category' => $category->slug]))->toBe([$pinned->id, $recent->id])
        ->and(listingIds(['category' => $category->slug, 'sort' => 'replies']))->toBe([$pinned->id, $recent->id])
        // Pinning is a category matter: the global listing ignores it.
        ->and(listingIds())->toBe([$elsewhere->id, $recent->id, $pinned->id])
        ->and(listingIds(['category' => 'missing-category']))->toBe([]);
});

it('narrows the listing to a tag', function () {
    $tag = Tag::factory()->create();
    $tagged = forumThread(tagIds: [$tag->id]);
    forumThread(tagIds: [Tag::factory()->create()->id]);

    expect(listingIds(['tag' => $tag->slug]))->toBe([$tagged->id])
        ->and(listingIds(['tag' => 'missing-tag']))->toBe([]);
});

it('keeps hidden, pending and deleted threads out of members\' listings', function () {
    $public = forumThread();
    $hidden = Thread::factory()->hidden()->create();
    $author = User::factory()->create();
    $pending = Thread::factory()->pending()->for($author, 'author')->create();
    $deleted = forumThread();
    app(DeleteThread::class)->handle($deleted, $deleted->author);

    expect(listingIds())->toBe([$public->id])
        ->and(listingIds(viewer: User::factory()->create()))->toBe([$public->id])
        ->and(listingIds(viewer: $author))->toEqualCanonicalizing([$public->id, $pending->id])
        // Staff see every status; deleted threads stay in the moderation tools.
        ->and(listingIds(viewer: User::factory()->moderator()->create()))
        ->toEqualCanonicalizing([$public->id, $hidden->id, $pending->id])
        // A ban takes those staff powers away.
        ->and(listingIds(viewer: forumUser('banned moderator')))->toBe([$public->id]);
});

it('paginates with the configured page size', function () {
    config(['forum.pagination.threads' => 2]);
    forumThread();
    forumThread();
    forumThread();

    $page = app(ThreadListing::class)->paginate([], null);

    expect($page->total())->toBe(3)
        ->and($page->perPage())->toBe(2)
        ->and($page->items())->toHaveCount(2);
});

it('returns the latest public threads for the home page', function () {
    $older = $this->travelTo(now()->subDay(), fn () => forumThread());
    $newer = forumThread();
    Thread::factory()->hidden()->create();

    $latest = app(ThreadListing::class)->latest(null, 5);

    expect($latest->modelKeys())->toBe([$newer->id, $older->id]);
});
