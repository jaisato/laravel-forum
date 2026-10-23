<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Resolving or dismissing a report: an optional note for the moderation
 * log and, when resolving, whether the post is hidden too.
 */
final class SettleReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Report $report */
        $report = $this->route('report');

        return $this->user()?->can('resolve', $report) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hide_post' => ['sometimes', 'boolean'],
            'approve_post' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
