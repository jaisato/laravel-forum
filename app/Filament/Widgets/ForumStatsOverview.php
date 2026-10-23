<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Enums\ThreadStatus;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Report;
use App\Models\Thread;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The dashboard's figures (spec §4.8): members, threads, posts and the open
 * reports. Unlike the home page's (ForumStats), they are live, not cached:
 * the staff act on them.
 */
final class ForumStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $week = now()->subDays(7);
        $openReports = Report::query()->where('status', ReportStatus::Open)->count();

        return [
            Stat::make(__('Members'), $this->number(User::query()->whereNotNull('email_verified_at')->count()))
                ->description($this->newThisWeek(User::query()->whereNotNull('email_verified_at')->where('created_at', '>=', $week)->count())),
            // Public threads and posts, as the denormalised counters keep them.
            Stat::make(__('Threads'), $this->number((int) Category::query()->sum('threads_count')))
                ->description($this->newThisWeek(Thread::query()->where('status', ThreadStatus::Published)->where('created_at', '>=', $week)->count())),
            Stat::make(__('Posts'), $this->number((int) Category::query()->sum('posts_count')))
                ->description($this->newThisWeek(Post::query()->where('status', PostStatus::Published)->where('created_at', '>=', $week)->count())),
            Stat::make(__('Open reports'), $this->number($openReports))
                ->color($openReports > 0 ? 'warning' : 'success')
                ->url(ReportResource::getUrl('index')),
        ];
    }

    private function number(int $value): string
    {
        return (string) Number::format($value, locale: app()->getLocale());
    }

    private function newThisWeek(int $count): string
    {
        return __(':count new in the last 7 days', ['count' => $this->number($count)]);
    }
}
