<?php

declare(strict_types=1);

use App\Actions\Users\DeleteAccount;
use App\Actions\Users\UpdateProfile;
use App\Models\User;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Asks to change the member's username through the settings page.
 */
function renameTo(User $member, string $username): TestResponse
{
    return actingAs($member)->patch(route('profile.update'), [
        'name' => $member->name,
        'email' => $member->email,
        'username' => $username,
    ]);
}

it('keeps a changed username from anyone else, but lets its owner take it back', function () {
    $ana = User::factory()->create(['username' => 'ana']);
    $other = User::factory()->create();

    renameTo($ana, 'ana_lopez')->assertSessionHasNoErrors();

    // Old @mentions link to /u/ana: nobody else may answer to it.
    renameTo($other, 'ana')->assertSessionHasErrors('username');

    renameTo($ana->fresh(), 'ana')->assertSessionHasNoErrors();

    expect($ana->fresh()->username)->toBe('ana');

    // The name she used in between stays hers.
    renameTo($other, 'ana_lopez')->assertSessionHasErrors('username');
});

it('keeps the username of an erased account from new members', function () {
    app(DeleteAccount::class)->handle(User::factory()->create(['username' => 'ana']));

    post(route('register.store'), [
        'name' => 'Otra Ana',
        'username' => 'ana',
        'email' => 'otra.ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('username');
});

it('sends an old profile address to the new one, and finds no erased one', function () {
    $ana = User::factory()->create(['username' => 'ana']);
    app(UpdateProfile::class)->handle($ana, ['username' => 'ana_lopez']);
    app(DeleteAccount::class)->handle(User::factory()->create(['username' => 'luis']));

    $this->get('/u/ana')->assertStatus(301)->assertRedirect('/u/ana_lopez');
    $this->get('/u/luis')->assertNotFound();
});
