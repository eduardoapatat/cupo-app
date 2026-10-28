<?php

namespace Database\Factories;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bookings made here skip BookSession, so they do not move the session's
 * booked_count. Tests about the count go through the action.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state: a student's confirmed booking.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'user_id' => User::factory(),
            'kind' => BookingKind::Student,
            'booked_by_id' => null,
        ];
    }

    /**
     * Someone of the team the front desk added to the session.
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => BookingKind::Staff,
        ]);
    }

    /**
     * A booking cancelled on time.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CancelledOnTime,
            'cancelled_at' => now(),
        ]);
    }
}
