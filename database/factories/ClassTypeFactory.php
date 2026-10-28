<?php

namespace Database\Factories;

use App\Models\ClassType;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassType>
 */
class ClassTypeFactory extends Factory
{
    /**
     * Define the model's default state: a 50-minute class for 10 people,
     * like the room of Estudio Flow.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'name' => 'Clase '.fake()->unique()->numberBetween(1, 100000),
            'duration_minutes' => 50,
            'capacity' => 10,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
