<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => $this->uniqueUsername(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Member,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(['role' => Role::Admin]);
    }

    public function moderator(): static
    {
        return $this->state(['role' => Role::Moderator]);
    }

    /**
     * A permanent ban, or a temporary one when an end date is given.
     */
    public function banned(?CarbonInterface $until = null): static
    {
        return $this->state(fn (array $attributes) => [
            'banned_at' => now()->subHour(),
            'banned_until' => $until,
            'ban_reason' => 'Repeated spam.',
        ]);
    }

    /**
     * Faker usernames may contain dots; forum usernames are [a-z0-9_-]{3,30}.
     */
    private function uniqueUsername(): string
    {
        $base = Str::of(fake()->unique()->userName())
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '_')
            ->limit(24, '')
            ->toString();

        return Str::padRight($base, 3, '_').'_'.fake()->unique()->numberBetween(10, 99999);
    }
}
