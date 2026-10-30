<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\VoidPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\VoidPaymentRequest;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * Void a payment taken by mistake, with its reason. It voids the plan it
 * paid for and cancels the bookings still to come.
 */
class PaymentVoidController extends Controller
{
    #[Authorize('payments.review', 'studio')]
    #[Authorize('void', 'payment')]
    public function store(VoidPaymentRequest $request, Studio $studio, Payment $payment, #[CurrentUser] User $user, VoidPayment $voidPayment): RedirectResponse
    {
        try {
            $voidPayment->handle($payment, $user, $request->reason());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We voided the payment and its plan.')]);
        } catch (PaymentRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.payments.show', [$studio, $payment]));
    }
}
