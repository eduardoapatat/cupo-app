<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Models\Booking;

/**
 * Attendance starts with everyone present: unticking someone marks a
 * no-show, and ticking her again undoes it. Whoever is not unticked counts
 * as attended once the attendance closes (attendance:close).
 */
class MarkNoShow
{
    /**
     * @param  bool  $afterClosing  Whether it may correct a class whose attendance already closed (bookings.override, checked by the caller).
     *
     * @throws BookingRejected when the attendance cannot be marked or the booking was cancelled.
     */
    public function handle(Booking $booking, bool $noShow, bool $afterClosing = false): Booking
    {
        $session = $booking->classSession;
        $isOpen = $session->attendanceIsOpen();
        $canCorrect = $afterClosing
            && $session->status === ClassSessionStatus::Scheduled
            && now()->greaterThanOrEqualTo($session->starts_at);

        if (! $isOpen && ! $canCorrect) {
            throw new BookingRejected(__('The attendance of this class cannot be marked now.'));
        }

        // Back to present is «confirmed» while the attendance is open, and
        // «attended» once closed, as attendance:close would have left it.
        $present = now()->greaterThan($session->attendanceClosesAt()) ? BookingStatus::Attended : BookingStatus::Confirmed;

        // Conditional, so it never touches a booking cancelled meanwhile.
        $marked = Booking::query()
            ->whereKey($booking->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Attended, BookingStatus::NoShow])
            ->update([
                'status' => $noShow ? BookingStatus::NoShow : $present,
                'updated_at' => now(),
            ]);

        if ($marked === 0) {
            throw new BookingRejected(__('This booking is no longer active.'));
        }

        return $booking->refresh();
    }
}
