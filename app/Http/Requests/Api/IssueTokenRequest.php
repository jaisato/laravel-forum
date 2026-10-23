<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Asking for an API token (POST /api/v1/auth/token) is signing in, as on the
 * login form: an email address or a username (a login without "@" is a
 * username, whichever field it comes in), the password and, for members who
 * use two-factor authentication, the code of their app or one of their
 * recovery codes. Who may hold a token is decided once the member is known
 * (AuthTokenController).
 */
final class IssueTokenRequest extends FormRequest
{
    /**
     * Wrong second factors a member may send in a minute, from any address:
     * the route's own limit counts per login and address, and whoever gets
     * this far already knows the password.
     */
    private const int TWO_FACTOR_ATTEMPTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required_without:username', 'nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            // The token's name, as on the settings page.
            'device_name' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in([...User::TOKEN_ABILITIES, ...User::STAFF_TOKEN_ABILITIES])],
            'code' => ['nullable', 'string', 'max:255'],
            'recovery_code' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.required_without' => __('Enter your email address or your username.')];
    }

    /**
     * The member signing in, once their password and their second factor
     * are right.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $field = $this->filled('email') ? 'email' : 'username';
        $user = User::findByLogin((string) $this->input($field));

        if ($user === null || ! Hash::check((string) $this->input('password'), $user->password)) {
            throw ValidationException::withMessages([$field => __('auth.failed')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->confirmSecondFactor($user);
        }

        return $user;
    }

    /**
     * The abilities asked for (when none are, all every member may give),
     * provided the member may give them.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function abilitiesFor(User $user): array
    {
        /** @var list<string> $abilities */
        $abilities = array_values($this->validated('abilities', User::TOKEN_ABILITIES));

        foreach ($abilities as $index => $ability) {
            if (! in_array($ability, $user->tokenAbilities(), true)) {
                throw ValidationException::withMessages([
                    "abilities.{$index}" => __('Only staff may give a token the :ability ability.', ['ability' => $ability]),
                ]);
            }
        }

        return $abilities;
    }

    /**
     * A recovery code is tried first, as on the challenge page, and works
     * once. Without either code the client learns that it must ask for one.
     */
    private function confirmSecondFactor(User $user): void
    {
        if (! $this->filled('code') && ! $this->filled('recovery_code')) {
            throw $this->twoFactorRequired('code', __('Enter the code of your authenticator app or one of your recovery codes.'));
        }

        $key = 'api-token-two-factor:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::TWO_FACTOR_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => $seconds]), headers: ['Retry-After' => $seconds]);
        }

        if ($this->filled('recovery_code')) {
            $code = collect($user->recoveryCodes())
                ->first(fn (string $code): bool => hash_equals($code, (string) $this->input('recovery_code')));

            if (is_string($code)) {
                $user->replaceRecoveryCode($code);
                RateLimiter::clear($key);

                return;
            }

            $field = 'recovery_code';
        } else {
            $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

            if (app(TwoFactorAuthenticationProvider::class)->verify($secret, (string) $this->input('code'))) {
                RateLimiter::clear($key);

                return;
            }

            $field = 'code';
        }

        RateLimiter::hit($key);

        throw $this->twoFactorRequired($field, __('The two-factor authentication code is not valid.'));
    }

    /**
     * The usual validation error, plus a flag clients can tell it by.
     */
    private function twoFactorRequired(string $field, string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
            'two_factor_required' => true,
        ], 422));
    }
}
