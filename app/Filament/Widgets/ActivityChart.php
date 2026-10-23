<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Queries\DailyActivity;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Threads, posts and new members of each of the last 30 days.
 */
final class ActivityChart extends ChartWidget
{
    private const int DAYS = 30;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('Activity');
    }

    public function getDescription(): string
    {
        return __('The last 30 days');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $activity = app(DailyActivity::class)->lastDays(self::DAYS);

        return [
            'labels' => array_map(
                fn (string $day): string => (new CarbonImmutable($day))->isoFormat('D MMM'),
                $activity['days'],
            ),
            'datasets' => [
                ['label' => __('Threads'), 'data' => $activity['threads'], 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b'],
                ['label' => __('Posts'), 'data' => $activity['posts'], 'borderColor' => '#6366f1', 'backgroundColor' => '#6366f1'],
                ['label' => __('New members'), 'data' => $activity['members'], 'borderColor' => '#22c55e', 'backgroundColor' => '#22c55e'],
            ],
        ];
    }
}
