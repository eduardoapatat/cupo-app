<?php

namespace Database\Factories;

use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Purchases made here skip SellPlan, so they have no payment. Tests about
 * selling go through the action.
 *
 * @extends Factory<PlanPurchase>
 */
class PlanPurchaseFactory extends Factory
{
    /**
     * Define the model's default state: 8 classes bought today, for 30 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return [
            'studio_id' => Studio::factory(),
            'user_id' => User::factory(),
            'plan_id' => null,
            'name' => '8 clases al mes',
            'price' => 45000,
            'credits' => 8,
            'credits_left' => 8,
            'validity_days' => 30,
            'daily_limit' => null,
            'starts_on' => $today->toDateString(),
            'expires_at' => $today->addDays(30)->utc(),
        ];
    }

    /**
     * An unlimited plan, with one class a day.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Ilimitado',
            'credits' => null,
            'credits_left' => null,
            'daily_limit' => 1,
        ]);
    }

    /**
     * A plan whose last day was yesterday.
     */
    public function expired(): static
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return $this->state(fn (array $attributes) => [
            'starts_on' => $today->subDays(30)->toDateString(),
            'expires_at' => $today->utc(),
        ]);
    }
}
