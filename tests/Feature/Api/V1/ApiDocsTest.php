<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('describes the API in OpenAPI 3.1, relative to /api/v1', function () {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', fn (string $version): bool => str_starts_with($version, '3.1.'))
        ->assertJsonPath('servers.0.url', url('/api/v1'))
        ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
        ->assertJsonStructure(['paths' => [
            '/auth/token',
            '/me',
            '/threads',
            '/threads/{thread}/posts',
            '/posts/{post}/reactions/{type}',
            '/notifications',
            '/conversations',
            '/moderation/reports',
        ]])
        // Signing in needs no token; reading takes one if there is one.
        ->assertJsonPath('paths./auth/token.post.security', [])
        ->assertJsonPath('paths./threads.get.security', [[], ['bearerAuth' => []]]);
});

it('shows the documentation page', function () {
    $this->get('/docs/api')->assertOk();
});

it('describes what it answers as schemas a client can be generated from', function () {
    $doc = $this->getJson('/docs/api.json')->assertOk()->json();
    $schemas = $doc['components']['schemas'];
    $refersTo = fn (string $schema, mixed $type): bool => str_contains((string) json_encode($type, JSON_UNESCAPED_SLASHES), "#/components/schemas/{$schema}");
    $response = fn (string $path, string $method, int $status): array => $doc['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'];

    // Members wherever they appear, null when the account was erased.
    expect(array_keys($schemas['UserResource']['properties']))->toBe(['id', 'name', 'username', 'avatar_url', 'role'])
        ->and($refersTo('UserResource', $schemas['ThreadResource']['properties']['author']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['ThreadResource']['properties']['last_poster']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['PostResource']['properties']['author']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['PostSummaryResource']['properties']['author']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['MessageResource']['properties']['author']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['ReportResource']['properties']['reporter']))->toBeTrue()
        ->and($refersTo('UserResource', $schemas['ConversationResource']['properties']['participants']))->toBeTrue();

    expect(array_keys($schemas['UserProfileResource']['properties']))->toContain('username', 'avatar_url', 'bio', 'reputation')
        ->and(array_keys($schemas['AccountResource']['properties']))->toContain('username', 'email', 'bio', 'unread_notifications', 'locale')
        ->and($refersTo('AccountResource', $response('/auth/token', 'post', 201)['properties']['user']))->toBeTrue()
        ->and(array_keys($response('/posts/{post}/reactions/{type}', 'put', 200)['properties']['data']['items']['properties']))
        ->toBe(['type', 'emoji', 'count', 'reacted'])
        ->and(array_keys($response('/notifications', 'get', 200)['properties']))->toContain('data', 'links', 'meta');

    // Public reads take a token when there is one; asking for one does not.
    expect($doc['paths']['/threads']['get']['security'])->toBe([[], ['bearerAuth' => []]])
        ->and($doc['paths']['/auth/token']['post']['security'])->toBe([]);
});

it('serves the documentation from its warm cache, and only so often to each visitor', function () {
    // Generating it is seconds of static analysis: the app container caches
    // it when it starts (docker/entrypoint/app-entrypoint.sh).
    config(['scramble.cache.store' => 'array']);
    Artisan::call('scramble:cache');

    foreach (range(1, 30) as $request) {
        $this->getJson('/docs/api.json')->assertOk();
    }

    $this->getJson('/docs/api.json')->assertTooManyRequests();
});
