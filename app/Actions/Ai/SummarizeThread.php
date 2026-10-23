<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\ThreadSummarizer;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Thread;
use Illuminate\Support\Collection;

/**
 * Asks the AI for the thread's summary and stores it, with the number of
 * replies it covers (Thread::summaryIsStale()). Only what the public sees
 * reaches the model; a long thread keeps its opening post and as many of
 * the latest replies as fit in forum.ai.summaries.max_input_characters. An
 * empty answer is not stored.
 */
final class SummarizeThread
{
    public function handle(Thread $thread): ?string
    {
        $summary = trim(ThreadSummarizer::make()->prompt($this->input($thread))->text);

        if ($summary === '') {
            return null;
        }

        $thread->forceFill([
            'ai_summary' => $summary,
            'ai_summary_generated_at' => now(),
            'ai_summary_replies_count' => $thread->replies_count,
        ])->save();

        return $summary;
    }

    private function input(Thread $thread): string
    {
        $budget = (int) config('forum.ai.summaries.max_input_characters');

        /** @var Collection<int, Post> $posts */
        $posts = $thread->posts()
            ->where('status', PostStatus::Published)
            ->with('author')
            ->orderBy('id')
            ->get();

        $opening = $posts->shift();
        $blocks = [];
        $used = 0;

        // The latest replies first, until the budget runs out.
        foreach ($posts->reverse() as $reply) {
            $block = $this->post($reply, $thread);

            if ($used + mb_strlen($block) > $budget) {
                break;
            }

            $used += mb_strlen($block);
            array_unshift($blocks, $block);
        }

        if ($opening !== null) {
            array_unshift($blocks, $this->post($opening, $thread));
        }

        return "Summarize this thread.\n\n<thread>\n<title>".$this->escape($thread->title)."</title>\n"
            .implode("\n", $blocks)."\n</thread>";
    }

    private function post(Post $post, Thread $thread): string
    {
        $author = $post->author === null || $post->author->trashed() ? 'deleted-user' : $post->author->username;
        $solution = (int) $thread->solution_post_id === $post->id ? ' solution="yes"' : '';

        return "<post author=\"{$author}\"{$solution}>\n".$this->escape($post->body)."\n</post>";
    }

    /**
     * Members' text may not open or close the tags around it.
     */
    private function escape(string $text): string
    {
        return str_replace(['<', '>'], ['&lt;', '&gt;'], $text);
    }
}
