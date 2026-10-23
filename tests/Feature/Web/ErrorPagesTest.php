<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.debug' => false]);
});

it('shows a missing page as the forum’s own, with its header', function () {
    $member = User::factory()->create(['username' => 'ana']);

    $this->actingAs($member)
        ->get('/no-existe')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 404)
            ->where('auth.user.username', 'ana')
            ->where('locale', 'en'));
});

it('shows forbidden pages, server errors and too many requests the same way', function (Closure $cause, int $status) {
    $this->get($cause())
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', $status));
})->with([
    'forbidden' => [function (): string {
        test()->actingAs(User::factory()->banned()->create());

        return route('threads.create');
    }, 403],
    'a server error' => [function (): string {
        Route::middleware('web')->get('/_tests/boom', fn () => throw new RuntimeException('Boom'));

        return '/_tests/boom';
    }, 500],
    'too many requests' => [function (): string {
        foreach (range(1, 60) as $i) {
            test()->get('/search?q=colas');
        }

        return '/search?q=colas';
    }, 429],
]);

it('shows maintenance as the forum’s own page too', function () {
    app()->maintenanceMode()->activate([]);

    try {
        $this->get('/')
            ->assertStatus(503)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 503)
                ->where('auth.user', null)
                ->has('locales'));
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

it('sends an expired form back to its page with a warning', function () {
    Route::middleware('web')->post('/_tests/expired', fn () => throw new TokenMismatchException);

    $this->from('/threads')
        ->post('/_tests/expired')
        ->assertRedirect('/threads')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'The page expired. Please try again.']);
});

it('sends an expired edit back with a GET, so the browser never repeats it', function (string $method) {
    Route::middleware('web')->{$method}('/_tests/expired', fn () => throw new TokenMismatchException);

    // A 302 keeps PATCH, PUT and DELETE: the browser would send them again,
    // with the same expired token, until it gave up.
    $this->from('/threads')
        ->call(strtoupper($method), '/_tests/expired')
        ->assertStatus(303)
        ->assertRedirect('/threads');
})->with(['patch', 'put', 'delete']);

it('leaves errors as they are while debugging', function () {
    config(['app.debug' => true]);

    $response = $this->get('/no-existe')->assertNotFound();

    expect($response->getContent())->not->toContain('"component":"Error"');
});

it('answers unknown API addresses as the API, without the session or CSRF of the web', function (string $method) {
    $route = app('router')->getRoutes()->match(Request::create('/api/v1/no-existe', strtoupper($method)));

    expect($route->gatherMiddleware())->toContain('api')->not->toContain('web');

    $this->call(strtoupper($method), '/api/v1/no-existe')
        ->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertCookieMissing(config('session.cookie'));
})->with(['get', 'post', 'patch', 'delete']);

it('answers JSON to JSON requests', function () {
    $this->getJson('/no-existe')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('does not know unknown addresses, whatever the method', function (string $method) {
    $this->json($method, '/no-existe')->assertNotFound();
})->with(['GET', 'POST', 'PATCH', 'DELETE']);
