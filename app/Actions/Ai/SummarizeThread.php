<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\ThreadSummarizer;
use App\Ai\UntrustedText;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Asks the AI for the thread's summary and stores it, with the number of
 * replies it covers (Thread::summaryIsStale()). Only what the public sees
 * reaches the model; a long thread keeps its opening post and as many of
 * the latest replies as fit in forum.ai.summaries.max_input_characters. An
 * empty answer is not stored.
 *
 * The provider takes a while, and a post it read may be edited, hidden or
 * deleted meanwhile, before there is a summary for ForgetThreadSummary to
 * forget. So the summary is stored only if those posts still read the
 * same and the thread still qualifies, checked under the thread's lock
 * that edits and moderation take too. Replies posted meanwhile do not
 * matter: the summary covers what it read.
 */
final class SummarizeThread
{
    public function handle(Thread $thread): ?string
    {
        $posts = $this->posts($thread);
        $input = $this->input($thread, $posts);
        $summary = trim(ThreadSummarizer::make()->prompt($input)->text);

        if ($summary === '') {
            return null;
        }

        return DB::transaction(function () use ($thread, $posts, $input, $summary): ?string {
            $current = Thread::query()->whereKey($thread->id)->lockForUpdate()->first();

            if ($current === null || ! $current->canHaveSummary()
                || $this->input($current, $this->published($current)->whereKey($posts->modelKeys())->get()) !== $input) {
                return null;
            }

            $current->forceFill([
                'ai_summary' => $summary,
                'ai_summary_generated_at' => now(),
                'ai_summary_replies_count' => $thread->replies_count,
            ])->save();

            return $summary;
        });
    }

    /**
     * The posts the summary reads, oldest first: the opening post and as
     * many of the latest replies as fit in the budget.
     *
     * @return Collection<int, Post>
     */
    private function posts(Thread $thread): Collection
    {
        $budget = (int) config('forum.ai.summaries.max_input_characters');
        $posts = $this->published($thread)->get();
        $opening = $posts->shift();
        $read = new Collection;
        $used = 0;

        // The latest replies first, until the budget runs out.
        foreach ($posts->reverse() as $reply) {
            $length = mb_strlen($this->post($reply, $thread));

            if ($used + $length > $budget) {
                break;
            }

            $used += $length;
            $read->prepend($reply);
        }

        if ($opening !== null) {
            $read->prepend($opening);
        }

        return $read;
    }

    /**
     * @return HasMany<Post, Thread>
     */
    private function published(Thread $thread): HasMany
    {
        return $thread->posts()
            ->where('status', PostStatus::Published)
            ->with('author')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private function input(Thread $thread, Collection $posts): string
    {
        return "Summarize this thread.\n\n<thread>\n".UntrustedText::wrap('title', $thread->title)."\n"
            .$posts->map(fn (Post $post): string => $this->post($post, $thread))->implode("\n")."\n</thread>";
    }

    private function post(Post $post, Thread $thread): string
    {
        $author = $post->author === null || $post->author->trashed() ? 'deleted-user' : $post->author->username;

        return UntrustedText::wrap('post', $post->body, [
            'author' => $author,
            ...((int) $thread->solution_post_id === $post->id ? ['solution' => 'yes'] : []),
        ]);
    }
}
