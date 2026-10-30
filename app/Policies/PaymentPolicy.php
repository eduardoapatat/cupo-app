<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * The front desk (payments.void_open) voids a payment while the register
     * it went into is still open. Once it closes, or for a payment outside
     * any register, only the owner (payments.void_closed) can, so whoever
     * received the money cannot erase it afterwards.
     */
    public function void(User $user, Payment $payment): Response
    {
        if ($payment->status !== PaymentStatus::Approved) {
            return Response::deny(__('This payment was already voided.'));
        }

        if ($user->can('payments.void_closed', $payment->studio)) {
            return Response::allow();
        }

        $inAnOpenRegister = $payment->cashRegister !== null && ! $payment->cashRegister->isClosed();

        return $inAnOpenRegister && $user->can('payments.void_open', $payment->studio)
            ? Response::allow()
            : Response::deny(__('Its register is already closed: only the owner can void it.'));
    }
}
