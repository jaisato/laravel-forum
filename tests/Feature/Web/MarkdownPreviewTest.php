<?php

declare(strict_types=1);

use App\Models\User;

it('renders Markdown the way posts are rendered', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('markdown.preview'), ['body' => '**Hola** <script>alert(1)</script>'])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, '<strong>Hola</strong>')
            && ! str_contains($html, '<script>'));
});

it('is for signed in members only', function () {
    $this->postJson(route('markdown.preview'), ['body' => 'Hola'])->assertUnauthorized();
});

it('validates the text', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('markdown.preview'), ['body' => str_repeat('a', 30001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});
