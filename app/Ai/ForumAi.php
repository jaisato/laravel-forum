<?php

declare(strict_types=1);

namespace App\Ai;

use InvalidArgumentException;

/**
 * Single source of truth for which AI features run and on which models.
 *
 * A feature runs only when the master switch (FORUM_AI_ENABLED) and its own
 * switch are both on, so an installation without provider keys never makes
 * a request and the interface hides the controls that depend on it.
 */
final class ForumAi
{
    public const array FEATURES = ['moderation', 'summaries', 'similar', 'tags'];

    public function enabled(string $feature): bool
    {
        $this->ensureKnown($feature);

        return (bool) config('forum.ai.enabled')
            && (bool) config("forum.ai.{$feature}.enabled");
    }

    /**
     * @return array<string, bool>
     */
    public function features(): array
    {
        $features = [];

        foreach (self::FEATURES as $feature) {
            $features[$feature] = $this->enabled($feature);
        }

        return $features;
    }

    public function textProvider(): string
    {
        return (string) config('forum.ai.text.provider');
    }

    public function textModel(string $feature): string
    {
        $this->ensureKnown($feature);

        $model = config("forum.ai.{$feature}.model");

        return filled($model) ? (string) $model : (string) config('forum.ai.text.model');
    }

    public function embeddingsProvider(): string
    {
        return (string) config('forum.ai.similar.provider');
    }

    public function embeddingsModel(): string
    {
        return (string) config('forum.ai.similar.model');
    }

    public function embeddingsDimensions(): int
    {
        return (int) config('forum.ai.similar.dimensions');
    }

    private function ensureKnown(string $feature): void
    {
        if (! in_array($feature, self::FEATURES, true)) {
            throw new InvalidArgumentException("Unknown AI feature [{$feature}].");
        }
    }
}
