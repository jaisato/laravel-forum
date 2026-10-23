<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

function verificationUrlFor(int $id, string $hashSource): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $id, 'hash' => sha1($hashSource)],
    );
}

it('renders the email verification screen', function () {
    actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk();
});

it('redirects unverified users to the verification prompt', function () {
    actingAs(User::factory()->unverified()->create())
        ->get(route('appearance.edit'))
        ->assertRedirect(route('verification.notice'));
});

it('verifies the email with a valid signed link', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    actingAs($user)
        ->get(verificationUrlFor($user->id, $user->email))
        ->assertRedirect(route('home', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not verify the email with an invalid hash', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    actingAs($user)->get(verificationUrlFor($user->id, 'wrong-email'));

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('does not verify the email for another user id', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    actingAs($user)->get(verificationUrlFor(123, $user->email));

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects verified users away from the verification prompt', function () {
    $user = User::factory()->create();
    Event::fake();

    actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route('home', absolute: false));

    Event::assertNotDispatched(Verified::class);
});

it('does not fire the verified event twice', function () {
    $user = User::factory()->create();
    Event::fake();

    actingAs($user)
        ->get(verificationUrlFor($user->id, $user->email))
        ->assertRedirect(route('home', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
