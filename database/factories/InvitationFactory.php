<?php

namespace Database\Factories;

use App\Enums\StudioRole;
use App\Models\Invitation;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state: a pending invitation for an
     * instructor, with the usual validity.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => StudioRole::Instructor,
            'expires_at' => now()->addDays(config()->integer('studio.invitation_days')),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
