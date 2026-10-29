<?php

namespace App\Enums;

/**
 * Why a class left or came back to a plan.
 */
enum CreditMovementReason: string
{
    /** She booked a class. */
    case Booking = 'booking';

    /** She, or the front desk for her, cancelled in time. */
    case CancelledOnTime = 'cancelled_on_time';

    /** The studio cancelled the class. */
    case SessionCancelled = 'session_cancelled';

    /** The owner returned it after the cancellation window (bookings.override). */
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Reserva',
            self::CancelledOnTime => 'Cancelada a tiempo',
            self::SessionCancelled => 'El estudio canceló la clase',
            self::Returned => 'Devuelta por el estudio',
        };
    }
}
