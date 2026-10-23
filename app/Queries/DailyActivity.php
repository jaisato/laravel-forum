<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The forum's activity day by day, for the admin dashboard's chart:
 * threads started, posts written and accounts opened, oldest day first.
 * Deleted threads, posts and accounts do not count.
 */
final class DailyActivity
{
    /**
     * @return array{days: list<string>, threads: list<int>, posts: list<int>, members: list<int>}
     */
    public function lastDays(int $days): array
    {
        $today = now();
        $from = $today->startOfDay()->subDays($days - 1);

        $range = [];

        for ($ago = $days - 1; $ago >= 0; $ago--) {
            $range[] = $today->subDays($ago)->toDateString();
        }

        return [
            'days' => $range,
            'threads' => $this->perDay(Thread::query(), $from, $range),
            'posts' => $this->perDay(Post::query(), $from, $range),
            'members' => $this->perDay(User::query(), $from, $range),
        ];
    }

    /**
     * How many rows of the query were created each day of the range. date()
     * works the same in PostgreSQL and SQLite.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $days
     * @return list<int>
     */
    private function perDay(Builder $query, CarbonInterface $from, array $days): array
    {
        $counts = $query->toBase()
            ->selectRaw('date(created_at) as day, count(*) as aggregate')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        return array_map(fn (string $day): int => (int) ($counts[$day] ?? 0), $days);
    }
}
