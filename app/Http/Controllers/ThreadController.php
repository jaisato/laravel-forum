<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\ForumAi;
use App\Http\Requests\ThreadIndexRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use App\Queries\ThreadListing;
use App\Services\ReadTracker;
use App\Services\ThreadSubscriptions;
use App\Services\ViewCounter;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ThreadController extends Controller
{
    private const int DESCRIPTION_LENGTH = 160;

    public function index(ThreadIndexRequest $request, ThreadListing $listing): Response
    {
        $filters = $request->listing();

        return Inertia::render('threads/Index', [
            'context' => null,
            'filters' => $filters,
            'threads' => ThreadResource::collection($listing->paginate($filters, $request->user())),
        ]);
    }

    /**
     * The thread page. ?post={id} (notification links) and ?unread=1 open
     * the page that holds that post and focus it. The posts shown are marked
     * as read and the visit is counted once per visitor and hour.
     */
    public function show(
        Request $request,
        Thread $thread,
        ReadTracker $reads,
        ViewCounter $views,
        ThreadSubscriptions $subscriptions,
        MarkdownRenderer $markdown,
        ForumAi $ai,
    ): Response {
        Gate::authorize('view', $thread);

        /** @var User|null $viewer */
        $viewer = $request->user();
        $perPage = (int) config('forum.pagination.posts');
        $focus = $this->focusedPost($request, $thread, $viewer, $reads);

        $posts = $this->visiblePosts($thread, $viewer)
            ->with(['author', 'replyTo.author'])
            ->withReactionSummary($viewer)
            ->orderBy('id')
            ->paginate($perPage, page: $focus === null ? null : $this->pageOf($focus, $thread, $viewer, $perPage));

        // The policies and the resource read the thread of every post.
        $posts->getCollection()->each->setRelation('thread', $thread);

        $views->record($thread, $request);

        $lastShown = $posts->getCollection()->last();

        if ($viewer !== null && $lastShown instanceof Post) {
            $reads->markRead($thread, $viewer, $lastShown->id);
        }

        $thread->loadMissing(['author', 'category', 'tags', 'lastPost.author', 'firstPost']);
        $can = $this->abilities($thread, $viewer);

        return Inertia::render('threads/Show', [
            'thread' => (new ThreadResource($thread))->detailed()->resolve($request),
            'posts' => PostResource::collection($posts),
            'focus' => $focus?->id,
            'can' => $can,
            'subscribed' => $viewer !== null && $subscriptions->isSubscribed($thread, $viewer),
            'meta' => [
                'title' => $thread->title,
                'description' => $markdown->excerpt($thread->firstPost->body ?? '', self::DESCRIPTION_LENGTH),
            ],
            ...($can['move'] ? ['moveTargets' => $this->moveTargets()] : []),
            // Filled by the similar threads (Task 19) and summaries (Task 28)
            // features; deferred so they never hold the page back.
            'similar' => Inertia::defer(fn (): array => []),
            'summary' => Inertia::defer(fn (): ?array => $this->summary($thread, $markdown, $ai)),
        ]);
    }

    /**
     * The posts the viewer may see; staff also see deleted ones, to restore
     * them.
     *
     * @return HasMany<Post, Thread>
     */
    private function visiblePosts(Thread $thread, ?User $viewer): HasMany
    {
        $posts = $thread->posts()->visibleTo($viewer);

        return $viewer?->canModerate() ? $posts->withTrashed() : $posts;
    }

    /**
     * The post to open the thread at: the one a link points to (?post=) or
     * the first one the member has not read yet (?unread=1, the last post
     * when everything is read).
     */
    private function focusedPost(Request $request, Thread $thread, ?User $viewer, ReadTracker $reads): ?Post
    {
        $linked = filter_var($request->query('post'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($linked !== false) {
            return $this->visiblePosts($thread, $viewer)->whereKey($linked)->first();
        }

        if ($viewer === null || ! $request->boolean('unread')) {
            return null;
        }

        return $reads->firstUnreadPost($thread, $viewer)
            ?? $this->visiblePosts($thread, $viewer)->latest('id')->first();
    }

    private function pageOf(Post $post, Thread $thread, ?User $viewer, int $perPage): int
    {
        $position = $this->visiblePosts($thread, $viewer)->where('id', '<=', $post->id)->count();

        return max(1, (int) ceil($position / $perPage));
    }

    /**
     * What the viewer may do with the thread, for the page's controls.
     *
     * @return array<string, bool>
     */
    private function abilities(Thread $thread, ?User $viewer): array
    {
        $may = fn (string $ability): bool => $viewer !== null && Gate::forUser($viewer)->allows($ability, $thread);
        $deleted = $thread->trashed();

        return [
            'reply' => ! $deleted && $may('reply'),
            'update' => ! $deleted && $may('update'),
            'delete' => ! $deleted && $may('delete'),
            'restore' => $deleted && $may('restore'),
            'pin' => ! $deleted && $may('pin'),
            'lock' => ! $deleted && $may('lock'),
            'move' => ! $deleted && $may('move'),
            'mark_solution' => ! $deleted && $may('markSolution'),
            'subscribe' => ! $deleted && $may('subscribe'),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function moveTargets(): array
    {
        return Category::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
            ->values()
            ->all();
    }

    /**
     * @return array{html: string, generated_at: string|null}|null
     */
    private function summary(Thread $thread, MarkdownRenderer $markdown, ForumAi $ai): ?array
    {
        if (! $ai->enabled('summaries') || $thread->ai_summary === null) {
            return null;
        }

        return [
            'html' => $markdown->render($thread->ai_summary),
            'generated_at' => $thread->ai_summary_generated_at?->toIso8601String(),
        ];
    }
}
