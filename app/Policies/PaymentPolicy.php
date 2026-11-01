<?php

namespace App\Policies;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * The receipt holds the student's bank details: only whoever reviews
     * payments and the student herself see it. Anyone else gets a 404.
     */
    public function viewReceipt(User $user, Payment $payment): Response
    {
        return $payment->user_id === $user->id || $user->can('payments.review', $payment->studio)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

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

        if ($payment->method === PaymentMethod::Webpay) {
            return Response::deny(__('It was paid with Webpay: only the owner can void it.'));
        }

        if ($payment->cashRegister === null) {
            return Response::deny(__('It came in from the app: only the owner can void it.'));
        }

        return ! $payment->cashRegister->isClosed() && $user->can('payments.void_open', $payment->studio)
            ? Response::allow()
            : Response::deny(__('Its register is already closed: only the owner can void it.'));
    }
}
