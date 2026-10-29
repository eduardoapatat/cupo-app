<?php

namespace App\Enums;

/**
 * Where a payment stands. A payment at the counter is approved at once;
 * pending, to review, rejected and voided arrive with transfer receipts
 * and the cash register (checkpoint 3.2).
 */
enum PaymentStatus: string
{
    case Approved = 'approved';
}
