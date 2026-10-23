<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeader;
use function Pest\Laravel\withSession;

function assertSharedLocale(string $expected): Closure
{
    return fn (Assert $page) => $page->where('locale', $expected)->etc();
}

it('prefers the signed-in user locale over the session and the browser', function () {
    $user = User::factory()->create(['locale' => 'es']);

    actingAs($user)
        ->withSession(['locale' => 'en'])
        ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get(route('home'))
        ->assertInertia(assertSharedLocale('es'));
});

it('prefers the session locale over the browser language', function () {
    withSession(['locale' => 'es'])
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('home'))
        ->assertInertia(assertSharedLocale('es'));
});

it('uses the browser language when it is supported', function () {
    config(['app.locale' => 'es']);

    withHeader('Accept-Language', 'fr-FR,fr;q=0.9,en;q=0.8')
        ->get(route('home'))
        ->assertInertia(assertSharedLocale('en'));
});

it('falls back to the application locale for unsupported browser languages', function () {
    config(['app.locale' => 'es']);

    withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(route('home'))
        ->assertInertia(assertSharedLocale('es'));
});

it('lists the supported locales for the language switcher', function () {
    get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->where('locales', ['es', 'en'])
        ->etc());
});

it('stores the chosen locale in the session for guests', function () {
    post(route('locale.update'), ['locale' => 'es'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'es');
});

it('persists the chosen locale on the signed-in user', function () {
    $user = User::factory()->create(['locale' => 'en']);

    actingAs($user)
        ->post(route('locale.update'), ['locale' => 'es'])
        ->assertRedirect();

    expect($user->refresh()->locale)->toBe('es');
});

it('rejects unsupported locales', function () {
    postJson(route('locale.update'), ['locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');
});

it('answers validation errors in the visitor language', function () {
    withHeader('Accept-Language', 'es-ES,es;q=0.9')
        ->postJson(route('locale.update'), ['locale' => 'fr'])
        ->assertJsonValidationErrors(['locale' => 'El valor seleccionado para idioma no es válido.']);
});
