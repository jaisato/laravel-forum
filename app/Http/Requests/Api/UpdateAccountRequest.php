<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changes to the member's own profile through the API (PATCH /api/v1/me):
 * only the fields sent change, under the settings page's rules. The email
 * address stays out of reach of tokens: one that could change it could take
 * the account over through a password reset.
 */
final class UpdateAccountRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', ...$this->nameRules()],
            'username' => ['sometimes', ...$this->usernameRules($user->id)],
            'email' => ['prohibited'],
            ...$this->aboutRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.prohibited' => __('Your email address can only be changed on the website.')];
    }

    /**
     * The fields UpdateProfile fills.
     *
     * @return array{name?: string, username?: string, bio?: string|null, location?: string|null, website?: string|null, locale?: string|null}
     */
    public function profile(): array
    {
        /** @var array{name?: string, username?: string, bio?: string|null, location?: string|null, website?: string|null, locale?: string|null} */
        return $this->validated();
    }
}
