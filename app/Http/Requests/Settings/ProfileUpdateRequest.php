<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

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
            ...$this->aboutRules(),
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
     * A banned member keeps their public profile as it is: they cannot
     * post, so they cannot rewrite how every post they wrote is signed
     * either. They may still clear what they told about themselves (and
     * remove their picture), and change their email and language.
     */
    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $member */
            $member = $this->user();

            if (! $member->isBanned()) {
                return;
            }

            $message = __('Your account is suspended: your public profile stays as it is until the suspension ends.');

            foreach (['name', 'username'] as $field) {
                if ($this->has($field) && $this->input($field) !== $member->{$field}) {
                    $validator->errors()->add($field, $message);
                }
            }

            foreach (['bio', 'location', 'website'] as $field) {
                if ($this->filled($field) && $this->input($field) !== $member->{$field}) {
                    $validator->errors()->add($field, $message);
                }
            }

            if ($this->hasFile('avatar')) {
                $validator->errors()->add('avatar', $message);
            }
        }];
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
