<?php

declare(strict_types=1);

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
        // Signing in and reading need no token.
        ->assertJsonPath('paths./auth/token.post.security', [])
        ->assertJsonPath('paths./threads.get.security', []);
});

it('shows the documentation page', function () {
    $this->get('/docs/api')->assertOk();
});
