<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The staff's report queue (GET /api/v1/moderation/reports): the open
 * reports unless another status is asked for.
 */
final class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Report::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(ReportStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function status(): ReportStatus
    {
        return ReportStatus::from((string) $this->validated('status', ReportStatus::Open->value));
    }
}
