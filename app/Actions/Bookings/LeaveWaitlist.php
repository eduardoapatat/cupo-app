<?php

namespace App\Actions\Bookings;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;

/**
 * Stop waiting for a spot. Conditional, so leaving at the same moment the
 * spot is given to her keeps the booking and fails here.
 */
class LeaveWaitlist
{
    /**
     * @throws BookingRejected when the entry is no longer waiting.
     */
    public function handle(WaitlistEntry $entry): void
    {
        $left = WaitlistEntry::query()
            ->whereKey($entry->id)
            ->where('status', WaitlistStatus::Waiting)
            ->update([
                'status' => WaitlistStatus::Left,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        if ($left === 0) {
            throw new BookingRejected(__('This place in the waitlist is no longer active.'));
        }
    }
}
