<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\ReviewPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\RejectPaymentRequest;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * Approve or reject a transfer receipt sent from the app.
 */
class PaymentReviewController extends Controller
{
    #[Authorize('payments.review', 'studio')]
    public function approve(Studio $studio, Payment $payment, #[CurrentUser] User $user, ReviewPayment $reviewPayment): RedirectResponse
    {
        try {
            $reviewPayment->approve($payment, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Approved. Her plan starts today.')]);
        } catch (PaymentRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return to_route('studios.payments.show', [$studio, $payment]);
    }

    #[Authorize('payments.review', 'studio')]
    public function reject(RejectPaymentRequest $request, Studio $studio, Payment $payment, #[CurrentUser] User $user, ReviewPayment $reviewPayment): RedirectResponse
    {
        try {
            $reviewPayment->reject($payment, $user, $request->reason());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Rejected. She will see why and can send another one.')]);
        } catch (PaymentRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return to_route('studios.payments.show', [$studio, $payment]);
    }
}
