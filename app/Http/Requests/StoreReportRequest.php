<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReportReason;
use App\Http\Requests\Concerns\AuthorizesVisibleContent;
use App\Models\Post;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReportRequest extends FormRequest
{
    use AuthorizesVisibleContent;

    public function authorize(): Response
    {
        /** @var Post $post */
        $post = $this->route('post');

        return $this->inspectVisible('report', $post);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }

    public function details(): ?string
    {
        $details = $this->validated('details');

        return is_string($details) && trim($details) !== '' ? trim($details) : null;
    }
}
