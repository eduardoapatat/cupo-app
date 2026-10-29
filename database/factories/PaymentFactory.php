<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\PlanPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state: the purchase paid in cash.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_purchase_id' => PlanPurchase::factory(),
            'studio_id' => fn (array $attributes) => PlanPurchase::query()->whereKey($attributes['plan_purchase_id'])->firstOrFail()->studio_id,
            'user_id' => fn (array $attributes) => PlanPurchase::query()->whereKey($attributes['plan_purchase_id'])->firstOrFail()->user_id,
            'amount' => 45000,
            'method' => PaymentMethod::Cash,
            'received_by_id' => null,
        ];
    }
}
