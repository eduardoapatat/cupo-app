<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;

/**
 * Save the attendance of a whole class at once: whoever is in the list
 * came, and everyone else holding a place did not. Each booking goes
 * through MarkAttendance, with its rules on when it can be marked; if one
 * fails, none is saved.
 */
class MarkSessionAttendance
{
    public function __construct(private MarkAttendance $markAttendance) {}

    /**
     * @param  array<int, int>  $attendedIds  The bookings of the class that came.
     * @param  bool  $afterClosing  Whether it may correct a class whose attendance already closed (bookings.override, checked by the caller).
     * @return array{attended: int, total: int}
     *
     * @throws BookingRejected when the attendance cannot be marked now.
     */
    public function handle(ClassSession $session, array $attendedIds, bool $afterClosing = false): array
    {
        return DB::transaction(function () use ($session, $attendedIds, $afterClosing): array {
            $bookings = $session->bookings()->current()->with('classSession')->orderBy('id')->get();

            foreach ($bookings as $booking) {
                $this->markAttendance->handle($booking, in_array($booking->id, $attendedIds, true), $afterClosing);
            }

            return [
                'attended' => $bookings->filter(fn (Booking $booking): bool => in_array($booking->id, $attendedIds, true))->count(),
                'total' => $bookings->count(),
            ];
        });
    }
}
