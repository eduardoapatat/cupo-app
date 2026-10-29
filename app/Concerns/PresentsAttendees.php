<?php

namespace App\Concerns;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;

/**
 * Who comes to a class, as «Mis clases», the Inicio and the Agenda show it:
 * names only, by name, with whether they came or missed it.
 */
trait PresentsAttendees
{
    /**
     * Uses the session's bookings when they are loaded, which must then be
     * the current ones with their user.
     *
     * @return array<int, array{id: int, name: string, isStaff: bool, attended: bool, noShow: bool}>
     */
    private function presentAttendees(ClassSession $session): array
    {
        $bookings = $session->relationLoaded('bookings')
            ? $session->bookings
            : $session->bookings()->current()->with('user')->get();

        return $bookings
            ->sortBy(fn (Booking $booking): string => mb_strtolower($booking->user->name))
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'name' => $booking->user->name,
                'isStaff' => $booking->kind === BookingKind::Staff,
                'attended' => $booking->status === BookingStatus::Attended,
                'noShow' => $booking->status === BookingStatus::NoShow,
            ])
            ->values()
            ->all();
    }
}
