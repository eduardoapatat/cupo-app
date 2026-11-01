<?php

namespace App\Enums;

/**
 * Where a payment stands. One taken at the counter is approved at once; a
 * transfer from the app waits to review until someone approves or rejects
 * it; one with Webpay is pending while she pays, and is approved or fails
 * when Transbank answers. An approved one taken by mistake is voided.
 */
enum PaymentStatus: string
{
    case ToReview = 'to_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Voided = 'voided';
    case Pending = 'pending';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::ToReview => 'Por validar',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Voided => 'Anulado',
            self::Pending => 'En Webpay',
            self::Failed => 'No se completó',
        };
    }
}
