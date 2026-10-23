<?php

declare(strict_types=1);

use App\Models\User;

it('lets guests make 60 requests a minute, counted per address', function () {
    // A different login each time: the token endpoint's own limit (five
    // tries per login and address) never trips, the general one does.
    $attempt = fn (int $request) => $this->postJson('/api/v1/auth/token', [
        'email' => "nadie{$request}@example.com",
        'password' => 'password',
        'device_name' => 'x',
    ]);

    // Of two limits, the headers show the one closer to running out.
    $attempt(1)
        ->assertUnprocessable()
        ->assertHeader('X-RateLimit-Limit', '5')
        ->assertHeader('X-RateLimit-Remaining', '4');

    foreach (range(2, 60) as $request) {
        $attempt($request)->assertUnprocessable();
    }

    $attempt(61)
        ->assertTooManyRequests()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/v1/auth/token', ['email' => 'nadie@example.com', 'password' => 'password', 'device_name' => 'x'])
        ->assertUnprocessable();
});

it('lets members make 120 requests a minute, counted per member', function () {
    $plain = User::factory()->create()->createToken('cli', ['read'])->plainTextToken;

    $this->withToken($plain)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');

    foreach (range(2, 120) as $request) {
        $this->withToken($plain)->getJson('/api/v1/me')->assertOk();
    }

    $this->withToken($plain)->getJson('/api/v1/me')->assertTooManyRequests();

    // Another member keeps their own allowance.
    $this->withToken(User::factory()->create()->createToken('cli', ['read'])->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk();
});

it('counts the public reads too, as a guest or as the member of the token', function () {
    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');

    $this->withToken(apiToken(User::factory()->create()))
        ->getJson('/api/v1/categories')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '120')
        ->assertHeader('X-RateLimit-Remaining', '119');
});

it('answers the forum limits with JSON, whatever the client accepts', function () {
    $thread = forumThread();
    $token = apiToken(User::factory()->create());

    foreach (range(1, 6) as $reply) {
        $this->withToken($token)
            ->post("/api/v1/threads/{$thread->id}/posts", ['body' => "Respuesta número {$reply}."])
            ->assertCreated();
    }

    $this->withToken($token)
        ->post("/api/v1/threads/{$thread->id}/posts", ['body' => 'Una de más.'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Too many attempts. Please wait a moment.');
});

it('limits how often a member edits, posts and threads alike', function () {
    $member = User::factory()->create();
    $thread = forumThread($member);
    $token = apiToken($member);

    foreach (range(1, 10) as $edit) {
        $this->withToken($token)
            ->patchJson("/api/v1/posts/{$thread->first_post_id}", ['body' => "Versión número {$edit} del mensaje."])
            ->assertOk();
    }

    $this->withToken($token)
        ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Un título más para el mismo hilo'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Too many attempts. Please wait a moment.');
});
