<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\ClassType;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * Define the model's default state: a 50-minute session of the studio's
     * own class type, starting at a different whole hour each time so
     * sessions of the same studio never overlap.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addHours(fake()->unique()->numberBetween(26, 300))->startOfHour();

        return [
            'studio_id' => Studio::factory(),
            'class_type_id' => fn (array $attributes) => ClassType::factory()->create(['studio_id' => $attributes['studio_id']]),
            'schedule_slot_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(50),
            'capacity' => 10,
            'instructor_id' => null,
        ];
    }

    /**
     * Start the session at the given time.
     */
    public function startingAt(\DateTimeInterface $startsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => Carbon::instance($startsAt)->addMinutes(50),
        ]);
    }
}
