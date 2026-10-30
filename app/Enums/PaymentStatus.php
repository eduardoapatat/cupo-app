<?php

namespace App\Enums;

/**
 * Where a payment stands. One taken at the counter is approved at once; a
 * transfer from the app waits to review until someone approves or rejects
 * it. An approved one taken by mistake is voided. Pending arrives with
 * Webpay (checkpoint 3.3).
 */
enum PaymentStatus: string
{
    case ToReview = 'to_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::ToReview => 'Por validar',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Voided => 'Anulado',
        };
    }
}
