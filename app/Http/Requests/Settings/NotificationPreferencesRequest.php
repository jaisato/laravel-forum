<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Every notification preference, each a yes or a no. Keys that are no
 * preference stay out (User::DEFAULT_PREFERENCES lists them).
 */
final class NotificationPreferencesRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_map(
            fn (): array => ['required', 'boolean'],
            User::DEFAULT_PREFERENCES,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function preferences(): array
    {
        return array_map(
            fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $this->safe()->only(array_keys(User::DEFAULT_PREFERENCES)),
        );
    }
}
