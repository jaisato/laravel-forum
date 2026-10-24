<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Forum\CreateThread;
use App\Actions\Forum\DeleteThread;
use App\Actions\Forum\UpdateThread;
use App\Ai\ForumAi;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\StoreThreadRequest;
use App\Http\Requests\ThreadIndexRequest;
use App\Http\Requests\UpdateThreadRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\ThreadResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\User;
use App\Queries\ThreadListing;
use App\Queries\ThreadPosts;
use App\Services\Ai\SimilarThreadFinder;
use App\Services\ReadTracker;
use App\Services\ThreadSubscriptions;
use App\Services\ViewCounter;
use App\Support\Markdown\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

final class ThreadController extends Controller
{
    use FlashesToasts;

    private const int DESCRIPTION_LENGTH = 160;

    /** Shorter titles say too little to look for similar threads. */
    private const int SIMILAR_MIN_LENGTH = 10;

    public function index(ThreadIndexRequest $request, ThreadListing $listing): Response
    {
        $filters = $request->listing();

        return Inertia::render('threads/Index', [
            'context' => null,
            'filters' => $filters,
            'threads' => ThreadResource::collection($listing->paginate($filters, $request->user())),
            'can' => ['create_thread' => Gate::allows('createAny', Thread::class)],
        ]);
    }

    /**
     * The new thread form: the categories the member may post in (the one
     * they came from, ?category=, preselected), the tags, and the similar
     * threads to the title they type (?title=), refreshed by partial reloads.
     */
    public function create(Request $request, SimilarThreadFinder $finder): Response
    {
        Gate::authorize('createAny', Thread::class);

        /** @var User $member */
        $member = $request->user();
        $categories = Category::query()->ordered()->get()
            ->filter(fn (Category $category): bool => $category->acceptsThreadsFrom($member))
            ->values();
        // No more of it than a title may have: the search gets what the form sends.
        $title = is_string($request->query('title'))
            ? trim(mb_substr($request->query('title'), 0, (int) config('forum.threads.title_max')))
            : '';

        return Inertia::render('threads/Create', [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'is_locked' => $category->is_locked,
            ])->all(),
            'tags' => $this->tagOptions(),
            'category' => $categories->firstWhere('slug', $request->query('category'))?->id,
            'similar' => fn (): array => mb_strlen($title) < self::SIMILAR_MIN_LENGTH
                ? []
                : $this->similarHints($member, $title, $finder),
        ]);
    }

    public function store(StoreThreadRequest $request, CreateThread $createThread): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();
        /** @var Category $category */
        $category = $request->category();

        $thread = $createThread->handle(
            $author,
            $category,
            $request->string('title')->value(),
            $request->string('body')->value(),
            $request->tagIds(),
        );

        $this->toast('Thread created.');

        return to_route('threads.show', $thread);
    }

    public function update(UpdateThreadRequest $request, Thread $thread, UpdateThread $updateThread): RedirectResponse
    {
        /** @var User $editor */
        $editor = $request->user();

        $updateThread->handle($thread, $editor, $request->title(), $request->tagIds());

        $this->toast('Thread updated.');

        return back();
    }

    public function destroy(Request $request, Thread $thread, DeleteThread $deleteThread): RedirectResponse
    {
        Gate::authorize('view', $thread);
        Gate::authorize('delete', $thread);

        /** @var User $actor */
        $actor = $request->user();

        $deleteThread->handle($thread, $actor);

        $this->toast('Thread deleted.');

        return to_route('categories.show', $thread->category);
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
        ThreadPosts $threadPosts,
        SimilarThreadFinder $finder,
        MarkdownRenderer $markdown,
        ForumAi $ai,
    ): Response {
        Gate::authorize('view', $thread);

        /** @var User|null $viewer */
        $viewer = $request->user();
        $focus = $this->focusedPost($request, $thread, $viewer, $reads, $threadPosts);
        $posts = $threadPosts->paginate($thread, $viewer, $focus === null ? null : $this->pageOf($focus, $thread, $viewer, $threadPosts));

        $views->record($thread, $request);

        $lastShown = $posts->getCollection()->last();

        if ($viewer !== null && $lastShown instanceof Post) {
            $reads->markRead($thread, $viewer, $lastShown->id);
        }

        $thread->loadMissing(['author', 'category', 'tags', 'lastPost.author', 'firstPost']);
        $can = $this->abilities($thread, $viewer, $ai->enabled('summaries'));

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
            ...($can['update'] ? ['tagOptions' => $this->tagOptions()] : []),
            // Related threads and the AI summary: deferred so they never hold
            // the page back, and left out if they fail (the search engine is
            // down, say) rather than taking the thread off the screen.
            'similar' => Inertia::defer(fn (): array => $this->similarThreads($finder->similarTo($thread, (int) config('forum.ai.similar.limit'))), rescue: true),
            'summary' => Inertia::defer(fn (): ?array => $this->summary($thread, $markdown, $ai), rescue: true),
        ]);
    }

    /**
     * The post to open the thread at: the one a link points to (?post=) or
     * the first one the member has not read yet (?unread=1, the last post
     * when everything is read).
     */
    private function focusedPost(Request $request, Thread $thread, ?User $viewer, ReadTracker $reads, ThreadPosts $threadPosts): ?Post
    {
        $linked = filter_var($request->query('post'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($linked !== false) {
            return $threadPosts->visible($thread, $viewer)->whereKey($linked)->first();
        }

        if ($viewer === null || ! $request->boolean('unread')) {
            return null;
        }

        return $reads->firstUnreadPost($thread, $viewer)
            ?? $threadPosts->visible($thread, $viewer)->latest('id')->first();
    }

    private function pageOf(Post $post, Thread $thread, ?User $viewer, ThreadPosts $threadPosts): int
    {
        $position = $threadPosts->visible($thread, $viewer)->where('id', '<=', $post->id)->count();

        return max(1, (int) ceil($position / (int) config('forum.pagination.posts')));
    }

    /**
     * What the viewer may do with the thread, for the page's controls.
     *
     * @return array<string, bool>
     */
    private function abilities(Thread $thread, ?User $viewer, bool $summaries): array
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
            // "Summarize the thread", while AI summaries are on.
            'summarize' => $summaries && $may('summarize'),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function moveTargets(): array
    {
        return array_values(Category::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
            ->all());
    }

    /**
     * Every tag, for the tag pickers.
     *
     * @return list<array{id: int, name: string, slug: string, color: string|null}>
     */
    private function tagOptions(): array
    {
        return array_values(Tag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color'])
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])
            ->all());
    }

    /**
     * The threads like the title being typed on the new thread form. Each
     * lookup reaches the search engine (and, with vector search, the
     * embeddings provider), so a member gets forum.rate_limits.similar_hints
     * of them; past that, or when the lookup fails, the form just shows none.
     *
     * @return list<array{id: int, title: string, slug: string, replies_count: int, is_solved: bool}>
     */
    private function similarHints(User $member, string $title, SimilarThreadFinder $finder): array
    {
        $limit = config('forum.rate_limits.similar_hints');

        /** @var list<array{id: int, title: string, slug: string, replies_count: int, is_solved: bool}>|false $hints */
        $hints = RateLimiter::attempt(
            "similar-hints:{$member->id}",
            (int) $limit['attempts'],
            fn (): array => rescue(fn (): array => $this->similarThreads($finder->forText($title)), []),
            (int) $limit['minutes'] * 60,
        );

        return $hints === false ? [] : $hints;
    }

    /**
     * @param  Collection<int, Thread>  $threads
     * @return list<array{id: int, title: string, slug: string, replies_count: int, is_solved: bool}>
     */
    private function similarThreads(Collection $threads): array
    {
        return array_values($threads->map(fn (Thread $thread): array => [
            'id' => $thread->id,
            'title' => $thread->title,
            'slug' => $thread->slug,
            'replies_count' => $thread->replies_count,
            'is_solved' => $thread->isSolved(),
        ])->all());
    }

    /**
     * The stored AI summary while the thread still qualifies for one,
     * rendered with the posts' sanitising Markdown (the model's output is
     * not trusted), and whether it is stale.
     *
     * @return array{html: string, generated_at: string|null, stale: bool}|null
     */
    private function summary(Thread $thread, MarkdownRenderer $markdown, ForumAi $ai): ?array
    {
        if (! $ai->enabled('summaries') || $thread->ai_summary === null || ! $thread->canHaveSummary()) {
            return null;
        }

        return [
            'html' => $markdown->render($thread->ai_summary),
            'generated_at' => $thread->ai_summary_generated_at?->toIso8601String(),
            'stale' => $thread->summaryIsStale(),
        ];
    }
}
