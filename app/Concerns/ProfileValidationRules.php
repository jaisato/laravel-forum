<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\RetiredUsername;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * @var list<string>
     */
    protected const array RESERVED_USERNAMES = [
        'admin', 'administrator', 'api', 'deleted', 'docs', 'forum', 'help',
        'horizon', 'login', 'logout', 'moderator', 'register', 'root',
        'settings', 'staff', 'support', 'system',
    ];

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, list<string|NotIn|Unique>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($userId),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * What members tell about themselves on their public profile, and the
     * language of their interface and their emails.
     *
     * @return array<string, list<string|In>>
     */
    protected function aboutRules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:'.(int) config('forum.profiles.bio_max')],
            'location' => ['nullable', 'string', 'max:'.(int) config('forum.profiles.location_max')],
            // Profiles link it: web addresses only.
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            // Null: the language of the browser or the session.
            'locale' => ['nullable', 'string', Rule::in(Locale::supported())],
        ];
    }

    /**
     * Usernames are public handles used in profile URLs and @mentions, so
     * they are lower case ASCII and some are reserved for the forum itself.
     * The `deleted-` prefix is given to accounts erased on request.
     *
     * @return list<string|NotIn|Unique>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9_-]+$/',
            'not_regex:/^deleted-/',
            Rule::notIn(self::RESERVED_USERNAMES),
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
            // Left behind by someone else (RetiredUsername): their owner may
            // take theirs back, nobody else may.
            Rule::unique(RetiredUsername::class, 'username')
                ->where(fn (Builder $retired): Builder => $userId === null ? $retired : $retired->where('user_id', '!=', $userId)),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return list<string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return list<string|Unique>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
