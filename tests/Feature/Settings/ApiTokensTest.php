<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('is for verified members', function () {
    get(route('api-tokens.index'))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())
        ->get(route('api-tokens.index'))
        ->assertRedirect(route('verification.notice'));
});

it('lists the tokens of the member and the abilities they may give them', function (Closure $member, array $abilities) {
    $user = $member();
    $token = $user->createToken('Mi script', ['read', 'write'])->accessToken;
    User::factory()->create()->createToken('Ajeno');

    actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->where('abilities', $abilities)
            ->has('tokens', 1)
            ->where('tokens.0', [
                'id' => $token->id,
                'name' => 'Mi script',
                'abilities' => ['read', 'write'],
                'last_used_at' => null,
                'created_at' => $token->created_at->toIso8601String(),
            ]));
})->with([
    'a member' => [fn () => User::factory()->create(), ['read', 'write', 'messages']],
    'a moderator' => [fn () => User::factory()->moderator()->create(), ['read', 'write', 'messages', 'moderate']],
]);

it('creates a token and shows it only once', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), ['name' => '  Mi script  ', 'abilities' => ['read', 'write']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('api-tokens.index'))
        ->assertInertiaFlash('token')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Token created. Copy it now: it will not be shown again.']);

    $plain = $response->baseResponse->getSession()?->get(SessionKey::FLASH_DATA)['token'];
    $token = PersonalAccessToken::findToken($plain);

    expect($token?->tokenable->is($user))->toBeTrue()
        ->and($token->name)->toBe('Mi script')
        ->and($token->abilities)->toBe(['read', 'write']);
});

it('gives the moderate ability to staff only', function () {
    actingAs(User::factory()->create())
        ->post(route('api-tokens.store'), ['name' => 'Moderación', 'abilities' => ['read', 'moderate']])
        ->assertSessionHasErrors('abilities.1');

    expect(PersonalAccessToken::query()->count())->toBe(0);

    actingAs(User::factory()->moderator()->create())
        ->post(route('api-tokens.store'), ['name' => 'Moderación', 'abilities' => ['read', 'moderate']])
        ->assertSessionHasNoErrors();

    expect(PersonalAccessToken::query()->sole()->abilities)->toBe(['read', 'moderate']);
});

it('validates the token', function (array $sent, string $field) {
    actingAs(User::factory()->create())
        ->post(route('api-tokens.store'), $sent)
        ->assertSessionHasErrors($field);

    expect(PersonalAccessToken::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => '', 'abilities' => ['read']], 'name'],
    'a long name' => [['name' => str_repeat('a', 101), 'abilities' => ['read']], 'name'],
    'no abilities' => [['name' => 'Mi script', 'abilities' => []], 'abilities'],
    'an unknown ability' => [['name' => 'Mi script', 'abilities' => ['admin']], 'abilities.0'],
]);

it('refuses new tokens to banned members', function () {
    actingAs(User::factory()->banned()->create())
        ->post(route('api-tokens.store'), ['name' => 'Mi script', 'abilities' => ['read']])
        ->assertForbidden();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes a token of the member', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Mi script')->accessToken;

    actingAs($user)
        ->from(route('api-tokens.index'))
        ->delete(route('api-tokens.destroy', $token->id))
        ->assertRedirect(route('api-tokens.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Token revoked.']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('does not revoke the tokens of others', function () {
    $token = User::factory()->create()->createToken('Ajeno')->accessToken;

    actingAs(User::factory()->create())
        ->delete(route('api-tokens.destroy', $token->id))
        ->assertNotFound();

    expect($token->fresh())->not->toBeNull();
});
