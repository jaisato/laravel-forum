<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

it('redirects to login when no challenge is pending', function () {
    get(route('two-factor.login'))->assertRedirect(route('login'));
});

it('renders the two-factor challenge after a password login', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});
