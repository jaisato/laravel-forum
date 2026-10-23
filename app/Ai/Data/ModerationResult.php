<?php

declare(strict_types=1);

namespace App\Ai\Data;

use App\Enums\ModerationVerdict;
use App\Enums\ReportReason;
use Illuminate\Support\Str;

/**
 * The AI moderator's answer, checked against its schema before the forum
 * acts on it: a model may answer anything, and nothing it says is trusted
 * blindly (spec §4.6).
 */
final readonly class ModerationResult
{
    /**
     * What a post may be flagged for.
     */
    public const array CATEGORIES = [
        'spam', 'harassment', 'hate', 'sexual', 'violence', 'self_harm', 'illegal', 'personal_data', 'other',
    ];

    private const int REASON_MAX = 500;

    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public ModerationVerdict $verdict,
        public array $categories,
        public float $confidence,
        public string $reason,
    ) {}

    /**
     * The answer, or null when it is not what the schema asks for: an
     * unknown verdict, a confidence outside 0..1, categories that are no
     * list or no reason. Unknown categories are dropped.
     *
     * @param  array<mixed>  $output
     */
    public static function fromArray(array $output): ?self
    {
        $verdict = is_string($output['verdict'] ?? null) ? ModerationVerdict::tryFrom($output['verdict']) : null;
        $confidence = $output['confidence'] ?? null;
        $categories = $output['categories'] ?? null;
        $reason = $output['reason'] ?? null;

        if ($verdict === null
            || ! (is_int($confidence) || is_float($confidence)) || $confidence < 0 || $confidence > 1
            || ! is_array($categories) || ! array_is_list($categories)
            || ! is_string($reason) || trim($reason) === '') {
            return null;
        }

        return new self(
            $verdict,
            array_values(array_unique(array_intersect(array_filter($categories, is_string(...)), self::CATEGORIES))),
            (float) $confidence,
            Str::limit(trim($reason), self::REASON_MAX),
        );
    }

    /**
     * The reason of the report the staff gets, from the first category.
     */
    public function reportReason(): ReportReason
    {
        return match ($this->categories[0] ?? null) {
            'spam' => ReportReason::Spam,
            'harassment', 'hate', 'sexual', 'violence' => ReportReason::Abuse,
            'illegal' => ReportReason::Illegal,
            default => ReportReason::Other,
        };
    }
}
