<?php

namespace Database\Factories;

use App\Models\ClassType;
use App\Models\ScheduleSlot;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleSlot>
 */
class ScheduleSlotFactory extends Factory
{
    /**
     * Define the model's default state: a class of the studio's own type,
     * on a weekday at a whole hour, without an instructor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'class_type_id' => fn (array $attributes) => ClassType::factory()->create(['studio_id' => $attributes['studio_id']]),
            'weekday' => fake()->numberBetween(1, 6),
            'starts_at' => sprintf('%02d:00:00', fake()->numberBetween(7, 20)),
            'instructor_id' => null,
        ];
    }
}
