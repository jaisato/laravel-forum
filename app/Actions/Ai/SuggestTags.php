<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Ai\Agents\TagSuggester;
use App\Ai\UntrustedText;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tags for a new thread, chosen by the AI among the forum's own: anything
 * else it answers is dropped, at most forum.ai.tags.max are kept, and a
 * provider that fails suggests nothing (and is logged) rather than
 * breaking the form (spec §4.6).
 */
final class SuggestTags
{
    /**
     * @return Collection<int, Tag>
     */
    public function handle(string $title, string $body): Collection
    {
        $tags = Tag::query()->orderBy('name')->get();

        if ($tags->isEmpty()) {
            return $tags;
        }

        $slugs = $tags->pluck('slug')->all();

        try {
            $output = TagSuggester::make(tags: $slugs)->prompt(
                "Suggest tags for this thread.\n\nTags: ".implode(', ', $slugs)."\n\n"
                .UntrustedText::wrap('title', $title)."\n\n".UntrustedText::wrap('post', $body),
            )->toArray();
        } catch (Throwable $exception) {
            Log::warning('Tag suggestions are unavailable.', ['exception' => $exception->getMessage()]);

            return new Collection;
        }

        if (! is_array($output['tags'] ?? null)) {
            Log::warning('The tag suggester gave an answer outside its schema.', ['output' => $output]);

            return new Collection;
        }

        $bySlug = $tags->keyBy(fn (Tag $tag): string => Str::lower($tag->slug));

        return new Collection(collect($output['tags'])
            ->filter(fn (mixed $slug): bool => is_string($slug))
            ->map(fn (string $slug): ?Tag => $bySlug->get(Str::lower(trim($slug))))
            ->filter()
            ->unique('id')
            ->take((int) config('forum.ai.tags.max'))
            ->values()
            ->all());
    }
}
