<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Entries made here skip JoinWaitlist, so they do not check that the
 * session is full. Tests about those rules go through the action.
 *
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state: someone waiting.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'user_id' => User::factory(),
            'added_by_id' => null,
        ];
    }
}
