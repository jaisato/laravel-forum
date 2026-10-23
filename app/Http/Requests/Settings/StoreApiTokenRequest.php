<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new API token: a name to recognise it by and the abilities it carries,
 * among those the member may give (User::tokenAbilities()). Banned members
 * get none: banning revokes their tokens.
 */
final class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canParticipate() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in($user->tokenAbilities())],
        ];
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        /** @var list<string> $abilities */
        $abilities = array_values($this->validated('abilities'));

        return $abilities;
    }
}
