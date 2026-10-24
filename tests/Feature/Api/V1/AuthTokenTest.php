<?php

declare(strict_types=1);

use App\Actions\Moderation\BanUser;
use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

/**
 * A member with two-factor authentication set up for real, and its secret.
 *
 * @return array{0: User, 1: string}
 */
function memberWithTwoFactor(): array
{
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->create([
        'username' => 'ana',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    return [$user, $secret];
}

describe('issuing', function () {
    it('gives a token for the email or the username and the password', function (array $login) {
        $user = User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);

        $response = $this->postJson('/api/v1/auth/token', [...$login, 'password' => 'password', 'device_name' => 'Mi script'])
            ->assertCreated()
            ->assertJsonPath('abilities', ['read', 'write', 'messages'])
            ->assertJsonPath('user.username', 'ana')
            ->assertJsonPath('user.email', 'ana@example.com');

        $token = PersonalAccessToken::findToken($response->json('token'));

        expect($token->name)->toBe('Mi script')
            ->and($token->tokenable->is($user))->toBeTrue()
            ->and($token->abilities)->toBe(['read', 'write', 'messages']);

        $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk();
    })->with([
        'an email' => [['email' => 'Ana@Example.com']],
        'a username' => [['username' => 'Ana']],
        'a username where the email goes' => [['email' => 'ana']],
    ]);

    it('refuses wrong credentials', function (array $sent, string $field) {
        User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);

        $this->postJson('/api/v1/auth/token', [...$sent, 'device_name' => 'Mi script'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(PersonalAccessToken::query()->count())->toBe(0);
    })->with([
        'a wrong password' => [['email' => 'ana@example.com', 'password' => 'wrong'], 'email'],
        'nobody by that name' => [['username' => 'nadie', 'password' => 'password'], 'username'],
        'no login' => [['password' => 'password'], 'email'],
        'no password' => [['email' => 'ana@example.com'], 'password'],
    ]);

    it('needs a device name', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password'])
            ->assertJsonValidationErrors('device_name');
    });

    it('gives the abilities asked for, among those the member may give', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'Lector', 'abilities' => ['read']])
            ->assertCreated()
            ->assertJsonPath('abilities', ['read']);

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'Moderación', 'abilities' => ['read', 'moderate']])
            ->assertJsonValidationErrors('abilities.1');
    });

    it('gives the moderate ability to staff who ask for it', function () {
        User::factory()->moderator()->create(['email' => 'mod@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'mod@example.com', 'password' => 'password', 'device_name' => 'Moderación', 'abilities' => ['read', 'moderate']])
            ->assertCreated()
            ->assertJsonPath('abilities', ['read', 'moderate']);
    });

    it('refuses banned members, once their password is right', function () {
        $member = User::factory()->create(['email' => 'ana@example.com']);
        app(BanUser::class)->handle($member, User::factory()->moderator()->create(), 'Spam');

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'Mi script'])
            ->assertForbidden();

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'wrong', 'device_name' => 'Mi script'])
            ->assertUnprocessable();

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('refuses members who have not verified their email yet, like the settings page', function () {
        User::factory()->unverified()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'Mi script'])
            ->assertForbidden();

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('slows down whoever guesses passwords', function () {
        User::factory()->create(['email' => 'ana@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => "wrong-{$attempt}", 'device_name' => 'x'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/token', ['email' => 'ana@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertTooManyRequests();
    });

    it('never tells through its limit which email belongs to which username', function () {
        User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);
        User::factory()->create(['email' => 'bea@example.com', 'username' => 'bea']);
        $status = fn (string $login): int => $this->postJson('/api/v1/auth/token', ['username' => $login, 'password' => 'wrong', 'device_name' => 'x'])->status();

        foreach (range(1, 5) as $attempt) {
            $status('ana');
        }

        // Counting per account would refuse ana's email, and only hers.
        expect($status('ana@example.com'))->toBe($status('bea@example.com'));
    });

    it('counts the guesses on a name together, on the login form and here', function () {
        User::factory()->create(['email' => 'ana@example.com', 'username' => 'ana']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => 'ana@example.com', 'password' => "wrong-{$attempt}"]);
        }

        $this->postJson('/api/v1/auth/token', ['username' => 'Ana@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertTooManyRequests();
    });
});

describe('two-factor authentication', function () {
    it('asks members who use it for their code', function () {
        memberWithTwoFactor();

        $this->postJson('/api/v1/auth/token', ['username' => 'ana', 'password' => 'password', 'device_name' => 'Mi script'])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonValidationErrors('code');

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('accepts the code of the authenticator app', function () {
        [, $secret] = memberWithTwoFactor();

        $this->postJson('/api/v1/auth/token', [
            'username' => 'ana',
            'password' => 'password',
            'device_name' => 'Mi script',
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertCreated();
    });

    it('refuses a wrong code', function () {
        memberWithTwoFactor();

        $this->postJson('/api/v1/auth/token', ['username' => 'ana', 'password' => 'password', 'device_name' => 'Mi script', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    });

    it('accepts each recovery code once', function () {
        [$user] = memberWithTwoFactor();
        $attempt = fn () => $this->postJson('/api/v1/auth/token', ['username' => 'ana', 'password' => 'password', 'device_name' => 'Mi script', 'recovery_code' => 'recovery-code-1']);

        $attempt()->assertCreated();
        $attempt()->assertUnprocessable()->assertJsonValidationErrors('recovery_code');

        expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1')
            ->and($user->fresh()->recoveryCodes())->toContain('recovery-code-2');
    });

    it('stops whoever guesses codes after five tries, from whatever address', function () {
        [, $secret] = memberWithTwoFactor();

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->postJson('/api/v1/auth/token', ['username' => 'ana', 'password' => 'password', 'device_name' => 'x', 'code' => '000000'])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.6'])
            ->postJson('/api/v1/auth/token', [
                'username' => 'ana',
                'password' => 'password',
                'device_name' => 'x',
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertTooManyRequests();

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('asks banned members for their code before telling them they are banned', function () {
        [$member] = memberWithTwoFactor();
        app(BanUser::class)->handle($member, User::factory()->moderator()->create(), 'Spam');

        $this->postJson('/api/v1/auth/token', ['username' => 'ana', 'password' => 'password', 'device_name' => 'Mi script'])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true);
    });
});

describe('revoking', function () {
    it('revokes the token the request comes with', function () {
        $user = User::factory()->create();
        $plain = $user->createToken('Mi script')->plainTextToken;
        $other = $user->createToken('Otro');

        $this->withToken($plain)->deleteJson('/api/v1/auth/token')->assertNoContent();

        expect(PersonalAccessToken::findToken($plain))->toBeNull()
            ->and($other->accessToken->fresh())->not->toBeNull();
    });

    it('needs a token', function () {
        $this->deleteJson('/api/v1/auth/token')->assertUnauthorized();
    });
});
