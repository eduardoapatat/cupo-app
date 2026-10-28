<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    /**
     * A student cancels her own bookings only. Someone else's answers 404,
     * so she cannot tell it exists.
     */
    public function cancelOwn(User $user, Booking $booking): Response
    {
        return $booking->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
