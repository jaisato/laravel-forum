<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Emails are stored in lower case (User mutator), so the uniqueness
     * check must see that same value: otherwise "Ana@Example.com" passes
     * validation and then collides with ana@example.com in the database.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        // The username is editable but optional here, so forms that do not
        // show it (and API clients) can update the rest of the profile.
        $rules['username'] = ['sometimes', ...$rules['username']];

        return $rules;
    }
}
