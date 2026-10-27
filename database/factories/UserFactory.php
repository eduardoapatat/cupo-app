<?php

namespace Database\Factories;

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
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
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'adult_declared_at' => now(),
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
     * A student enrolled at the counter who does not use the app: a name and
     * a phone, no email and no password.
     */
    public function withoutApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'phone' => '+56912345678',
        ]);
    }

    /**
     * Make the user a member of the studio with the given role.
     */
    public function memberOf(Studio $studio, StudioRole $role): static
    {
        return $this->has(Membership::factory()->for($studio)->role($role));
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
}
