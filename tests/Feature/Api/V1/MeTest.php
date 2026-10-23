<?php

declare(strict_types=1);

use App\Models\User;

it('shows the members themselves: their profile, their email and their counters', function () {
    $user = User::factory()->create([
        'name' => 'Ana López',
        'username' => 'ana',
        'email' => 'ana@example.com',
        'bio' => 'Desarrolladora PHP.',
        'locale' => 'es',
    ]);

    $this->withToken($user->createToken('cli', ['read'])->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'ana')
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.bio', 'Desarrolladora PHP.')
        ->assertJsonPath('data.locale', 'es')
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.unread_notifications', 0)
        ->assertJsonPath('data.unread_conversations', 0);
});

it('needs a token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('needs the abilities the request uses', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('cli', ['write'])->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertForbidden();

    $this->withToken($user->createToken('cli', ['read'])->plainTextToken)
        ->patchJson('/api/v1/me', ['name' => 'Otro nombre'])
        ->assertForbidden();
});

it('updates the profile, only what is sent', function () {
    $user = User::factory()->create(['username' => 'ana', 'email' => 'ana@example.com', 'location' => 'Madrid']);

    $this->withToken($user->createToken('cli', ['read', 'write'])->plainTextToken)
        ->patchJson('/api/v1/me', [
            'name' => 'Ana López',
            'bio' => 'Desarrolladora PHP.',
            'website' => 'https://ana.example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana López')
        ->assertJsonPath('data.bio', 'Desarrolladora PHP.')
        ->assertJsonPath('data.website', 'https://ana.example.com');

    expect($user->fresh())
        ->name->toBe('Ana López')
        ->username->toBe('ana')
        ->email->toBe('ana@example.com')
        ->location->toBe('Madrid')
        ->bio->toBe('Desarrolladora PHP.')
        ->website->toBe('https://ana.example.com');
});

it('validates the profile', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('cli', ['write'])->plainTextToken)
        ->patchJson('/api/v1/me', ['name' => '', 'website' => 'javascript:alert(1)', 'username' => 'admin', 'locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'website', 'username', 'locale']);
});

it('keeps the email out of reach of tokens', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);

    $this->withToken($user->createToken('cli', ['write'])->plainTextToken)
        ->patchJson('/api/v1/me', ['email' => 'otra@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($user->fresh()->email)->toBe('ana@example.com');
});
