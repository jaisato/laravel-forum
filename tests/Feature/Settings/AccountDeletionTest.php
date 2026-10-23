<?php

declare(strict_types=1);

use App\Models\ThreadRead;
use App\Models\ThreadSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

/**
 * A member with every kind of personal data the forum keeps.
 */
function memberWithPersonalData(): User
{
    Storage::fake(config('forum.profiles.avatar.disk'));
    Storage::disk(config('forum.profiles.avatar.disk'))->put('avatars/ana.webp', 'image');

    $user = User::factory()->withTwoFactor()->create([
        'name' => 'Ana García',
        'username' => 'ana',
        'email' => 'ana@example.com',
        'bio' => 'Desarrolladora en Valencia.',
        'location' => 'Valencia',
        'website' => 'https://ana.example.com',
        'locale' => 'es',
        'last_seen_at' => now(),
    ]);
    $user->forceFill(['avatar_path' => 'avatars/ana.webp'])->save();
    $user->createToken('cli');
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Portátil',
        'credential_id' => 'credential-1',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Their thread gets a reply: subscription, read marker and notification.
    forumReply(forumThread($user));

    return $user;
}

it('erases the personal data of the account', function () {
    $user = memberWithPersonalData();

    expect($user->notifications()->count())->toBe(1);

    actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    assertGuest();

    $erased = User::withTrashed()->findOrFail($user->id);

    expect($erased->trashed())->toBeTrue()
        ->and($erased->name)->toBe('')
        ->and($erased->username)->toBe("deleted-{$user->id}")
        ->and($erased->email)->toBe("deleted-{$user->id}@deleted.invalid")
        ->and($erased->email_verified_at)->toBeNull()
        ->and($erased->bio)->toBeNull()
        ->and($erased->location)->toBeNull()
        ->and($erased->website)->toBeNull()
        ->and($erased->locale)->toBeNull()
        ->and($erased->avatar_path)->toBeNull()
        ->and($erased->last_seen_at)->toBeNull()
        ->and($erased->two_factor_secret)->toBeNull()
        ->and($erased->two_factor_recovery_codes)->toBeNull()
        ->and($erased->two_factor_confirmed_at)->toBeNull()
        ->and(Hash::check('password', $erased->password))->toBeFalse()
        ->and($erased->tokens()->count())->toBe(0)
        ->and(DB::table('passkeys')->where('user_id', $user->id)->count())->toBe(0)
        ->and(ThreadSubscription::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(ThreadRead::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($erased->notifications()->count())->toBe(0);

    Storage::disk(config('forum.profiles.avatar.disk'))->assertMissing('avatars/ana.webp');
});

it('erases the notifications the account caused for others', function () {
    $user = memberWithPersonalData();
    $starter = User::factory()->create();
    $thread = forumThread($starter);
    // The member replies: the starter is told who did, by name.
    forumReply($thread, $user, 'Mi respuesta.');
    // Somebody else replies too: that notification is not theirs to erase.
    forumReply($thread);

    expect($starter->notifications()->count())->toBe(2);

    actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect($starter->notifications()->count())->toBe(1)
        ->and($starter->notifications()->sole()->data['actor']['username'])->not->toBe('ana');
});

it('keeps the posts and credits them to «Deleted user»', function () {
    $user = memberWithPersonalData();

    actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $this->get(route('threads.index'))->assertInertia(fn (Assert $page) => $page
        ->has('threads.data', 1)
        ->where('threads.data.0.author', null));

    expect(DB::table('posts')->where('user_id', $user->id)->count())->toBe(1);
});

it('frees the email for a new account, but not the username old mentions point at', function () {
    $user = memberWithPersonalData();

    actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $register = fn (string $username) => $this->post(route('register.store'), [
        'name' => 'Ana de nuevo',
        'username' => $username,
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $register('ana')->assertSessionHasErrors('username');
    $register('ana_de_nuevo')->assertSessionHasNoErrors()->assertRedirect(route('home', absolute: false));

    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeTrue();
});
