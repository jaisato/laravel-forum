<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

it('displays the profile page', function () {
    actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

it('updates the profile information', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

it('sends a verification link to a new email address', function () {
    Notification::fake();
    $user = User::factory()->create();

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'nuevo@example.com',
    ]);

    // Unverified members cannot post, so they need the link right away.
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('normalises the email before checking it is free', function () {
    User::factory()->create(['email' => 'alice@example.com']);
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => ' Alice@Example.COM ',
        ])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => ' Bob.New@Example.COM ',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('bob.new@example.com');
});

it('keeps the verification status when the email is unchanged', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('keeps the verification status when only the case of the email changes', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@example.com']);

    actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'Ana@Example.com',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

it('lets users delete their account', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    assertGuest();
    // Accounts are soft deleted so the member's posts keep their author row.
    expect($user->fresh()->trashed())->toBeTrue();
});

it('requires the correct password to delete the account', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

describe('the public profile', function () {
    it('shows the member what others see of them, to edit it', function () {
        $user = User::factory()->create([
            'username' => 'ana',
            'bio' => 'Desarrolladora PHP.',
            'location' => 'Sevilla',
            'website' => 'https://ana.example.com',
            'locale' => 'en',
        ]);

        actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('profile', [
                    'username' => 'ana',
                    'bio' => 'Desarrolladora PHP.',
                    'location' => 'Sevilla',
                    'website' => 'https://ana.example.com',
                    'locale' => 'en',
                    'avatar_url' => null,
                ]));
    });

    it('saves the username, the biography, the location, the website and the language', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Ana López',
                'username' => 'ana_lopez',
                'email' => $user->email,
                'bio' => "Desarrolladora PHP.\nEn Sevilla.",
                'location' => 'Sevilla',
                'website' => 'https://ana.example.com',
                'locale' => 'en',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        expect($user->fresh())
            ->name->toBe('Ana López')
            ->username->toBe('ana_lopez')
            ->bio->toBe("Desarrolladora PHP.\nEn Sevilla.")
            ->location->toBe('Sevilla')
            ->website->toBe('https://ana.example.com')
            ->locale->toBe('en');
    });

    it('forgets what the member leaves empty', function () {
        $user = User::factory()->create(['bio' => 'Hola', 'location' => 'Sevilla', 'website' => 'https://ana.example.com', 'locale' => 'en']);

        actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'bio' => '',
                'location' => '',
                'website' => '',
                'locale' => '',
            ])
            ->assertSessionHasNoErrors();

        // No language of their own: the browser's or the session's again.
        expect($user->fresh())
            ->bio->toBeNull()
            ->location->toBeNull()
            ->website->toBeNull()
            ->locale->toBeNull();
    });

    it('keeps the public profile of a banned member as it is, except to clear it', function () {
        $member = User::factory()->banned()->create(['bio' => 'Hola', 'location' => 'Sevilla']);
        $unchanged = ['name' => $member->name, 'email' => $member->email];

        foreach ([
            'name' => ['name' => 'Otro nombre'],
            'username' => ['username' => 'otro_nombre'],
            'bio' => ['bio' => 'Visitad mi tienda.'],
            'location' => ['location' => 'Cádiz'],
            'website' => ['website' => 'https://tienda.example.com'],
        ] as $field => $change) {
            actingAs($member)
                ->patch(route('profile.update'), [...$unchanged, 'bio' => 'Hola', 'location' => 'Sevilla', ...$change])
                ->assertSessionHasErrors($field);
        }

        uploadAvatar($member, UploadedFile::fake()->image('foto.png', 300, 300))->assertSessionHasErrors('avatar');

        // Their own data, though, they may take down, and their email and
        // language stay theirs to change.
        actingAs($member)
            ->patch(route('profile.update'), [...$unchanged, 'email' => 'nueva@example.com', 'bio' => '', 'location' => '', 'locale' => 'en'])
            ->assertSessionHasNoErrors();

        expect($member->fresh())
            ->name->toBe($unchanged['name'])
            ->bio->toBeNull()
            ->location->toBeNull()
            ->avatar_path->toBeNull()
            ->email->toBe('nueva@example.com')
            ->locale->toBe('en');
    });

    it('keeps usernames unique', function () {
        User::factory()->create(['username' => 'ana']);
        $user = User::factory()->create();

        actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'username' => 'ana'])
            ->assertSessionHasErrors('username');

        expect($user->fresh()->username)->not->toBe('ana');
    });

    it('validates the profile', function (array $changes, string $field) {
        $user = User::factory()->create();

        actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, ...$changes])
            ->assertSessionHasErrors($field);
    })->with([
        'a long biography' => [['bio' => str_repeat('a', 501)], 'bio'],
        'a long location' => [['location' => str_repeat('a', 81)], 'location'],
        'a script for a website' => [['website' => 'javascript:alert(1)'], 'website'],
        'a website without its scheme' => [['website' => 'ana.example.com'], 'website'],
        'a language the forum does not speak' => [['locale' => 'fr'], 'locale'],
        'a reserved username' => [['username' => 'admin'], 'username'],
    ]);
});

describe('the avatar', function () {
    beforeEach(function () {
        Storage::fake(config('forum.profiles.avatar.disk'));
    });

    function uploadAvatar(User $user, UploadedFile $file): TestResponse
    {
        return actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ]);
    }

    it('stores the picture as a 256×256 WebP', function () {
        $user = User::factory()->create();

        uploadAvatar($user, UploadedFile::fake()->image('foto.png', 800, 600))->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;
        $stored = Storage::disk(config('forum.profiles.avatar.disk'))->get($path);
        $size = getimagesizefromstring((string) $stored);

        expect($path)->toStartWith('avatars/')->toEndWith('.webp')
            ->and([$size[0], $size[1], $size['mime']])->toBe([256, 256, 'image/webp'])
            ->and($user->fresh()->avatar_url)->toEndWith($path);
    });

    it('replaces the previous picture', function () {
        $user = User::factory()->create();
        $disk = Storage::disk(config('forum.profiles.avatar.disk'));

        uploadAvatar($user, UploadedFile::fake()->image('primera.jpg', 300, 300));
        $first = $user->fresh()->avatar_path;
        uploadAvatar($user, UploadedFile::fake()->image('segunda.jpg', 300, 300));
        $second = $user->fresh()->avatar_path;

        expect($second)->not->toBe($first);
        $disk->assertMissing($first);
        $disk->assertExists($second);
    });

    it('removes the picture', function () {
        $user = User::factory()->create();
        uploadAvatar($user, UploadedFile::fake()->image('foto.jpg', 300, 300));
        $path = $user->fresh()->avatar_path;

        actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'remove_avatar' => true])
            ->assertSessionHasNoErrors();

        expect($user->fresh()->avatar_path)->toBeNull();
        Storage::disk(config('forum.profiles.avatar.disk'))->assertMissing($path);
    });

    it('takes reasonable pictures only', function (Closure $file) {
        $user = User::factory()->create();

        uploadAvatar($user, $file())->assertSessionHasErrors('avatar');

        expect($user->fresh()->avatar_path)->toBeNull();
    })->with([
        'no picture' => [fn () => UploadedFile::fake()->create('notas.pdf', 100, 'application/pdf')],
        'too heavy' => [fn () => UploadedFile::fake()->image('foto.jpg', 300, 300)->size(2049)],
        'too large' => [fn () => UploadedFile::fake()->image('panorama.png', 4001, 1)],
    ]);
});
