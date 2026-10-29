<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Models\Booking;

/**
 * Whoever teaches the class marks who came. Nobody starts marked: someone
 * not marked when the attendance closes missed the class (attendance:close).
 */
class MarkAttendance
{
    /**
     * @param  bool  $afterClosing  Whether it may correct a class whose attendance already closed (bookings.override, checked by the caller).
     *
     * @throws BookingRejected when the attendance cannot be marked or the booking was cancelled.
     */
    public function handle(Booking $booking, bool $attended, bool $afterClosing = false): Booking
    {
        $session = $booking->classSession;
        $canCorrect = $afterClosing
            && $session->status === ClassSessionStatus::Scheduled
            && now()->greaterThanOrEqualTo($session->starts_at);

        if (! $session->attendanceIsOpen() && ! $canCorrect) {
            throw new BookingRejected(__('The attendance of this class cannot be marked now.'));
        }

        // Unmarked is «confirmed» while the attendance is open, and a
        // no-show once closed, as attendance:close would have left it.
        $absent = now()->greaterThan($session->attendanceClosesAt()) ? BookingStatus::NoShow : BookingStatus::Confirmed;

        // Conditional, so it never touches a booking cancelled meanwhile.
        $marked = Booking::query()
            ->whereKey($booking->id)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Attended, BookingStatus::NoShow])
            ->update([
                'status' => $attended ? BookingStatus::Attended : $absent,
                'updated_at' => now(),
            ]);

        if ($marked === 0) {
            throw new BookingRejected(__('This booking is no longer active.'));
        }

        return $booking->refresh();
    }
}
