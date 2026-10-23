<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Every preference, with the given changes.
 *
 * @param  array<string, bool>  $changes
 * @return array<string, bool>
 */
function preferences(array $changes = []): array
{
    return [...User::DEFAULT_PREFERENCES, ...$changes];
}

it('is for members only', function () {
    get(route('notifications.preferences.edit'))->assertRedirect(route('login'));
});

it('shows the member their preferences', function () {
    $user = User::factory()->create(['preferences' => ['email_mentions' => false]]);

    actingAs($user)
        ->get(route('notifications.preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')
            ->where('preferences', preferences(['email_mentions' => false])));
});

it('saves the preferences', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->put(route('notifications.preferences.update'), preferences([
            'email_replies' => false,
            'allow_private_messages' => false,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('notifications.preferences.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Preferences saved.']);

    expect($user->fresh()->preferences)->toBe(preferences([
        'email_replies' => false,
        'allow_private_messages' => false,
    ]))
        ->and($user->fresh()->wantsEmail('email_replies'))->toBeFalse()
        ->and($user->fresh()->acceptsPrivateMessages())->toBeFalse();
});

it('keeps out what is no preference', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->put(route('notifications.preferences.update'), [...preferences(), 'is_admin' => true])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->preferences)->toBe(preferences());
});

it('validates the preferences', function (array $sent, string $field) {
    actingAs(User::factory()->create())
        ->put(route('notifications.preferences.update'), $sent)
        ->assertSessionHasErrors($field);
})->with([
    'a missing preference' => [array_diff_key(User::DEFAULT_PREFERENCES, ['email_replies' => true]), 'email_replies'],
    'something that is not yes or no' => [[...User::DEFAULT_PREFERENCES, 'email_mentions' => 'quizá'], 'email_mentions'],
]);
