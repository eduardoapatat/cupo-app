<?php

namespace App\Concerns;

use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * A payment as the lists of «Pagos», «Caja» and the student's profile show
 * it. Load user, plan, planPurchase and cashRegister first.
 */
trait PresentsPayments
{
    /**
     * @return array<string, mixed>
     */
    private function presentPayment(Payment $payment): array
    {
        $takenAt = $payment->created_at?->timezone(config()->string('studio.timezone'));
        $register = $payment->cashRegister;

        return [
            'id' => $payment->id,
            'date' => $takenAt?->translatedFormat('j \d\e F'),
            'time' => $takenAt?->format('H:i'),
            'studentName' => $payment->user->name,
            // A transfer to review has no purchase yet, only the plan she chose.
            'planName' => $payment->planPurchase->name ?? $payment->plan?->name,
            'method' => $payment->method->label(),
            'amount' => $payment->amount,
            'status' => $payment->status->value,
            'statusLabel' => $payment->status->label(),
            // Voided once its register was closed: it stays in its frozen totals.
            'voidedAfterClosing' => $payment->status === PaymentStatus::Voided
                && $register?->closed_at !== null
                && $payment->voided_at?->greaterThanOrEqualTo($register->closed_at) === true,
        ];
    }
}
