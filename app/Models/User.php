<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property Role $role
 * @property int $reputation
 * @property int $threads_count
 * @property int $posts_count
 * @property string|null $avatar_path
 * @property-read string|null $avatar_url
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $website
 * @property string|null $locale
 * @property array<string, bool> $preferences
 * @property CarbonImmutable|null $banned_at
 * @property CarbonImmutable|null $banned_until
 * @property string|null $ban_reason
 * @property CarbonImmutable|null $last_seen_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'locale', 'bio', 'location', 'website', 'preferences'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Notification preferences. A missing key means the default applies, so
     * new preferences reach existing members without a data migration.
     */
    public const array DEFAULT_PREFERENCES = [
        'email_replies' => true,
        'email_mentions' => true,
        'email_solutions' => true,
        'email_messages' => true,
        'email_moderation' => true,
        'allow_private_messages' => true,
    ];

    /**
     * The database defaults, so a model that was just saved behaves like a
     * reloaded one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Member->value,
        'reputation' => 0,
        'threads_count' => 0,
        'posts_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'reputation' => 'integer',
            'threads_count' => 'integer',
            'posts_count' => 'integer',
            'banned_at' => 'datetime',
            'banned_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Thread, $this>
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Private conversations the user takes part in, including the ones they
     * left (see the pivot's left_at).
     *
     * @return BelongsToMany<Conversation, $this, ConversationParticipant>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot(['id', 'last_read_message_id', 'last_read_at', 'left_at'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === Role::Moderator;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * A ban without an end date is permanent; a temporary one lapses on its
     * own, so no scheduled job is needed to lift it.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null
            && ($this->banned_until === null || $this->banned_until->isFuture());
    }

    /**
     * Whether the member may post, react, report or send private messages.
     */
    public function canParticipate(): bool
    {
        return $this->hasVerifiedEmail() && ! $this->isBanned();
    }

    /**
     * Staff powers (moderation and the admin panel), lost while banned: an
     * administrator may ban a moderator.
     */
    public function canModerate(): bool
    {
        return $this->isStaff() && ! $this->isBanned();
    }

    /**
     * Administration powers: categories, tags, roles and Horizon.
     */
    public function canAdminister(): bool
    {
        return $this->isAdmin() && ! $this->isBanned();
    }

    public function acceptsPrivateMessages(): bool
    {
        return $this->preferences['allow_private_messages'];
    }

    public function wantsEmail(string $type): bool
    {
        return (bool) ($this->preferences[$type] ?? false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOnline(Builder $query, int $minutes): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Stored lower case: PostgreSQL compares text case-sensitively, so the
     * login lookup and the unique index only work on a normalised value.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * @return Attribute<string, string>
     */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * @return Attribute<array<string, bool>, array<string, mixed>>
     */
    protected function preferences(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                /** @var array<string, bool> $stored */
                $stored = json_decode($value ?? '[]', true) ?: [];

                return [...self::DEFAULT_PREFERENCES, ...$stored];
            },
            set: fn (array $value): string => (string) json_encode(array_map(
                fn (mixed $enabled): bool => (bool) $enabled,
                array_intersect_key($value, self::DEFAULT_PREFERENCES),
            )),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path === null
            ? null
            : Storage::disk(config('forum.profiles.avatar.disk'))->url($this->avatar_path));
    }
}
