<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        return [
            ...$rules,
            'bio' => ['nullable', 'string', 'max:'.(int) config('forum.profiles.bio_max')],
            'location' => ['nullable', 'string', 'max:'.(int) config('forum.profiles.location_max')],
            // Profiles link it: web addresses only.
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            // Null: the language of the browser or the session.
            'locale' => ['nullable', 'string', Rule::in(Locale::supported())],
            // Bounded in bytes and in pixels: a small file can still decode
            // into a huge bitmap.
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:'.(int) config('forum.profiles.avatar.max_kilobytes'),
                'dimensions:max_width=4000,max_height=4000',
            ],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The fields UpdateProfile fills (the avatar travels apart).
     *
     * @return array{name?: string, username?: string, email?: string, bio?: string|null, location?: string|null, website?: string|null, locale?: string|null}
     */
    public function profile(): array
    {
        /** @var array{name?: string, username?: string, email?: string, bio?: string|null, location?: string|null, website?: string|null, locale?: string|null} */
        return $this->safe()->except(['avatar', 'remove_avatar']);
    }
}
