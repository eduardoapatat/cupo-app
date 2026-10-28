<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\User;

/**
 * What cancelling a booking did: the booking, now cancelled, and who got
 * the freed spot from the waitlist, if anyone.
 */
final readonly class BookingCancellation
{
    public function __construct(
        public Booking $booking,
        public ?User $promoted,
    ) {}
}
