<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state: a pack of 8 classes for 30 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'name' => fake()->unique()->numerify('Plan ###'),
            'price' => 45000,
            'credits' => 8,
            'validity_days' => 30,
            'daily_limit' => null,
        ];
    }

    /**
     * An unlimited plan, with one class a day.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'credits' => null,
            'daily_limit' => 1,
        ]);
    }

    /**
     * A plan the studio no longer sells.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
