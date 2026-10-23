<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Forum\DeletePost;
use App\Actions\Forum\ReplyToThread;
use App\Actions\Forum\UpdatePost;
use App\Http\Controllers\Concerns\FlashesToasts;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PostController extends Controller
{
    use FlashesToasts;

    /**
     * Opens the thread at the new reply.
     */
    public function store(StorePostRequest $request, Thread $thread, ReplyToThread $replyToThread): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();
        $replyTo = $request->filled('reply_to_id')
            ? Post::query()->find((int) $request->validated('reply_to_id'))
            : null;

        $post = $replyToThread->handle($thread, $author, (string) $request->validated('body'), $replyTo);

        $this->toast('Reply posted.');

        return to_route('threads.show', [$thread, 'post' => $post->id]);
    }

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $updatePost): RedirectResponse
    {
        /** @var User $editor */
        $editor = $request->user();

        $updatePost->handle($post, $editor, (string) $request->validated('body'));

        $this->toast('Post updated.');

        return back();
    }

    /**
     * Deleting the opening post deletes the thread: the member goes back to
     * its category.
     */
    public function destroy(Request $request, Post $post, DeletePost $deletePost): RedirectResponse
    {
        Gate::authorize('delete', $post);

        /** @var User $actor */
        $actor = $request->user();
        $isFirst = $post->isFirst();

        $deletePost->handle($post, $actor);

        if (! $isFirst) {
            $this->toast('Post deleted.');

            return back();
        }

        $this->toast('Thread deleted.');

        $category = Category::query()->findOrFail(Thread::withTrashed()->whereKey($post->thread_id)->value('category_id'));

        return to_route('categories.show', $category);
    }
}
