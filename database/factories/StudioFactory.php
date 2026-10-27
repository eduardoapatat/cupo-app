<?php

namespace Database\Factories;

use App\Enums\Commune;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Studio>
 */
class StudioFactory extends Factory
{
    /**
     * Define the model's default state: the default rules of every new
     * studio. The slug and the enrollment token are filled on creation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Estudio '.fake()->lastName(),
            'commune' => fake()->randomElement(Commune::cases()),
            ...Studio::defaultRules(),
        ];
    }
}
