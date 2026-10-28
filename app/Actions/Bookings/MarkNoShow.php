<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * Attendance marks only who missed the class: a booking still confirmed
 * becomes a no-show, or back when it was a mistake. Whoever is not marked
 * attended once the attendance closes (attendance:close).
 */
class MarkNoShow
{
    /**
     * @throws BookingRejected when the attendance is not open or the booking was cancelled.
     */
    public function handle(Booking $booking, bool $noShow): Booking
    {
        if (! $booking->classSession->attendanceIsOpen()) {
            throw new BookingRejected(__('The attendance of this class cannot be marked now.'));
        }

        // Conditional, so it never touches a booking cancelled meanwhile.
        $marked = Booking::query()
            ->whereKey($booking->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::NoShow])
            ->update([
                'status' => $noShow ? BookingStatus::NoShow : BookingStatus::Confirmed,
                'updated_at' => now(),
            ]);

        if ($marked === 0) {
            throw new BookingRejected(__('This booking is no longer active.'));
        }

        return $booking->refresh();
    }
}
