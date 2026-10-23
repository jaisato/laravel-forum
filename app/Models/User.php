<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
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
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail, PasskeyUser
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
     * API token abilities every member may grant (tokenAbilities()).
     */
    public const array TOKEN_ABILITIES = ['read', 'write', 'messages'];

    /**
     * API token abilities only staff may grant (tokenAbilities()).
     */
    public const array STAFF_TOKEN_ABILITIES = ['moderate'];

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

    /**
     * The member's notifications, newest first, as UserNotification models
     * (the database channel stores new ones through this relation too).
     *
     * @return MorphMany<UserNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(UserNotification::class, 'notifiable')->latest();
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
     * The member a login names: an email address or, without "@", a
     * username, in any case. The login form and the API token endpoint take
     * either in the same field.
     */
    public static function findByLogin(string $login): ?self
    {
        $login = Str::lower(trim($login));

        return self::query()->where(str_contains($login, '@') ? 'email' : 'username', $login)->first();
    }

    /**
     * The member as stored now. Actions authorised at the start of a request
     * check their rules again on this under lock: a moderator may have
     * banned the member while the request ran.
     */
    public function current(): static
    {
        return $this->fresh() ?? $this;
    }

    /**
     * Whether the member may post, react, report or send private messages.
     */
    public function canParticipate(): bool
    {
        return $this->hasVerifiedEmail() && ! $this->isBanned();
    }

    /**
     * Notifications and emails are written in the member's language.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Conversations with messages the user has not read yet; their own
     * messages are always read, and conversations they left do not count.
     */
    public function unreadConversationsCount(): int
    {
        return $this->conversations()
            ->wherePivotNull('left_at')
            ->whereColumn('conversations.last_message_id', '>', 'conversation_participants.last_read_message_id')
            ->count();
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

    /**
     * The admin panel is for staff (gate access-admin). Without this method
     * Filament would admit any signed in user in a local environment and
     * nobody at all in production.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return Gate::forUser($this)->allows('access-admin');
    }

    /**
     * The abilities the member may give an API token: reading, writing,
     * private messages and, for staff, moderation.
     *
     * @return list<string>
     */
    public function tokenAbilities(): array
    {
        return [...self::TOKEN_ABILITIES, ...($this->canModerate() ? self::STAFF_TOKEN_ABILITIES : [])];
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
     * The SQL side of canParticipate(): verified members who are not banned
     * right now (a temporary ban lapses on its own).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInGoodStanding(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('banned_at')
                ->orWhere('banned_until', '<=', now()));
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
