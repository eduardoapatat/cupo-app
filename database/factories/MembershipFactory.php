<?php

namespace Database\Factories;

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'user_id' => User::factory(),
            'role' => StudioRole::Student,
        ];
    }

    /**
     * Give the membership the given role.
     */
    public function role(StudioRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }
}
