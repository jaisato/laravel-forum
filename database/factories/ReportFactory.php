<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'reporter_id' => User::factory(),
            'source' => ReportSource::User,
            'reason' => fake()->randomElement(ReportReason::cases()),
            'details' => fake()->optional()->sentence(),
            'status' => ReportStatus::Open,
        ];
    }

    /**
     * A report raised by the AI moderator, which has no reporter account.
     */
    public function ai(float $confidence = 0.9): static
    {
        return $this->state(fn (): array => [
            'reporter_id' => null,
            'source' => ReportSource::Ai,
            'details' => fake()->sentence(),
            'ai_confidence' => $confidence,
        ]);
    }

    public function resolved(?User $moderator = null): static
    {
        return $this->closed(ReportStatus::Resolved, $moderator);
    }

    public function dismissed(?User $moderator = null): static
    {
        return $this->closed(ReportStatus::Dismissed, $moderator);
    }

    private function closed(ReportStatus $status, ?User $moderator): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'resolved_by_id' => $moderator ?? User::factory()->moderator(),
            'resolved_at' => now(),
        ]);
    }
}
