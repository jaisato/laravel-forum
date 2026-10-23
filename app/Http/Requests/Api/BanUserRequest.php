<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A ban from the moderation API: why (required, as in the panel) and, for a
 * temporary one, until when. Who may ban whom is UserPolicy::ban.
 */
final class BanUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()?->can('ban', $target) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    /**
     * Null: banned for good.
     */
    public function until(): ?CarbonImmutable
    {
        $until = $this->validated('until');

        return is_string($until) ? CarbonImmutable::parse($until) : null;
    }
}
