<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Keeps body_html consistent with whatever Markdown body a test passes in,
 * for models that store both (posts and private messages).
 */
trait FillsBodyHtml
{
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            if (blank($model->getAttribute('body_html'))) {
                $model->setAttribute('body_html', collect(preg_split("/\n{2,}/", (string) $model->getAttribute('body')) ?: [])
                    ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
                    ->implode("\n"));
            }
        });
    }
}
