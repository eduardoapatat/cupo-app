<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Registers made here skip OpenCashRegister. Closing one goes through
 * CloseCashRegister, or the closed() state for a register of another day.
 *
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * Define the model's default state: open today with $20.000.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'user_id' => User::factory(),
            'business_date' => now(config()->string('studio.timezone'))->toDateString(),
            'opening_cash' => 20000,
            'opened_at' => now(),
        ];
    }

    /**
     * A register of a day that already passed, opened that morning.
     */
    public function onDaysAgo(int $days): static
    {
        $day = now(config()->string('studio.timezone'))->subDays($days)->setTime(9, 0);

        return $this->state(fn (array $attributes) => [
            'business_date' => $day->toDateString(),
            'opened_at' => $day->utc(),
        ]);
    }

    /**
     * Closed with no sales, the cash counted as expected.
     */
    public function closed(): static
    {
        return $this->afterCreating(function (CashRegister $register): void {
            $register->forceFill([
                'closed_at' => $register->opened_at->addHours(12),
                'expected_cash' => $register->opening_cash,
                'counted_cash' => $register->opening_cash,
                'cash_total' => 0,
                'card_total' => 0,
                'transfer_total' => 0,
            ])->save();
        });
    }
}
