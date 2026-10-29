<?php

namespace App\Enums;

/**
 * Where a booking stands. Cancelling on time keeps the class (from phase 3,
 * the credit comes back); cancelling late uses it. Whoever teaches marks
 * who came (attended); once the attendance closes, a booking still
 * confirmed becomes a no-show.
 */
enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case CancelledOnTime = 'cancelled_on_time';
    case CancelledLate = 'cancelled_late';
    case Attended = 'attended';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Reservada',
            self::CancelledOnTime => 'Cancelada a tiempo',
            self::CancelledLate => 'Cancelada tarde',
            self::Attended => 'Asistió',
            self::NoShow => 'Faltó',
        };
    }

    public function isCancelled(): bool
    {
        return $this === self::CancelledOnTime || $this === self::CancelledLate;
    }
}
