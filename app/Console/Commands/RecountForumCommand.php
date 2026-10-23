<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Enums\ThreadStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes every denormalised counter from scratch, with the rule of
 * App\Services\ForumCounters: only visible content counts (published and
 * not deleted). Reputation is what members earned: reactions received on
 * any of their posts, and solutions to threads of others.
 *
 * Each statement only touches rows whose value is wrong, so the command
 * reports what it fixed. Portable SQL: PostgreSQL and SQLite 3.39+.
 */
#[Description('Recompute the denormalised forum counters and reputation')]
final class RecountForumCommand extends Command
{
    protected $signature = 'forum:recount';

    public function handle(): int
    {
        $published = PostStatus::Published->value;
        $publishedThread = ThreadStatus::Published->value;

        $fixes = [
            'threads.replies_count' => $this->fix('threads', 'replies_count',
                'select count(*) from posts where posts.thread_id = threads.id and posts.status = ? and posts.deleted_at is null and posts.id <> coalesce(threads.first_post_id, 0)',
                [$published]),
            'threads.last_post_id' => $this->fix('threads', 'last_post_id',
                'select max(posts.id) from posts where posts.thread_id = threads.id and posts.status = ? and posts.deleted_at is null',
                [$published]),
            'threads.last_posted_at' => $this->fix('threads', 'last_posted_at',
                'select coalesce(max(posts.created_at), threads.created_at) from posts where posts.id = threads.last_post_id',
                []),
            'categories.threads_count' => $this->fix('categories', 'threads_count',
                'select count(*) from threads where threads.category_id = categories.id and threads.status = ? and threads.deleted_at is null',
                [$publishedThread]),
            'categories.posts_count' => $this->fix('categories', 'posts_count',
                'select count(*) from posts inner join threads on threads.id = posts.thread_id where threads.category_id = categories.id and threads.status = ? and threads.deleted_at is null and posts.status = ? and posts.deleted_at is null',
                [$publishedThread, $published]),
            'tags.threads_count' => $this->fix('tags', 'threads_count',
                'select count(*) from tag_thread inner join threads on threads.id = tag_thread.thread_id where tag_thread.tag_id = tags.id and threads.status = ? and threads.deleted_at is null',
                [$publishedThread]),
            'users.threads_count' => $this->fix('users', 'threads_count',
                'select count(*) from threads where threads.user_id = users.id and threads.status = ? and threads.deleted_at is null',
                [$publishedThread]),
            'users.posts_count' => $this->fix('users', 'posts_count',
                'select count(*) from posts inner join threads on threads.id = posts.thread_id where posts.user_id = users.id and posts.status = ? and posts.deleted_at is null and threads.status = ? and threads.deleted_at is null',
                [$published, $publishedThread]),
            'users.reputation' => $this->fix('users', 'reputation',
                'select ? * (select count(*) from reactions inner join posts on posts.id = reactions.post_id where posts.user_id = users.id)'
                .' + ? * (select count(*) from threads inner join posts on posts.id = threads.solution_post_id where posts.user_id = users.id and threads.user_id <> users.id)',
                [(int) config('forum.reputation.reaction'), (int) config('forum.reputation.solution')]),
        ];

        foreach ($fixes as $counter => $fixed) {
            $this->components->twoColumnDetail($counter, $fixed === 0 ? '<fg=green>ok</>' : "<fg=yellow>{$fixed} fixed</>");
        }

        array_sum($fixes) === 0
            ? $this->components->info('All counters were already right.')
            : $this->components->warn(array_sum($fixes).' values were wrong and have been fixed.');

        return self::SUCCESS;
    }

    /**
     * Sets the column to the subquery wherever they differ, and returns how
     * many rows that was.
     *
     * @param  list<int|string>  $bindings
     */
    private function fix(string $table, string $column, string $subquery, array $bindings): int
    {
        return DB::update(
            "update {$table} set {$column} = ({$subquery}) where {$column} is distinct from ({$subquery})",
            [...$bindings, ...$bindings],
        );
    }
}
