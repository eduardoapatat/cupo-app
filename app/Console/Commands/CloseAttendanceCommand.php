<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('attendance:close')]
#[Description('Mark as attended the bookings nobody marked as a no-show once their attendance closes')]
class CloseAttendanceCommand extends Command
{
    /**
     * Execute the console command. It runs every night: the attendance of
     * a class closes at the end of the day after it, so every class that
     * started before yesterday is closed by now.
     */
    public function handle(): int
    {
        $closedBefore = now(config()->string('studio.timezone'))->startOfDay()->subDay();

        $attended = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('classSession', fn (Builder $session) => $session->where('starts_at', '<', $closedBefore->utc()))
            ->update(['status' => BookingStatus::Attended, 'updated_at' => now()]);

        $this->info("Bookings marked as attended: {$attended}.");

        return self::SUCCESS;
    }
}
