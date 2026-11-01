<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\StartWebpayPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\BuyWithWebpayRequest;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Comprar plan» with Webpay: the student starts the payment, and a page
 * sends her browser to pay it at Transbank (it must get there by POST).
 */
class WebpayPaymentController extends Controller
{
    #[Authorize('self.buy_plan', 'studio')]
    public function store(BuyWithWebpayRequest $request, Studio $studio, #[CurrentUser] User $user, StartWebpayPayment $startWebpayPayment): RedirectResponse
    {
        $plan = $studio->plans()->findOrFail($request->integer('plan_id'));

        try {
            $payment = $startWebpayPayment->handle($plan, $user);

            return to_route('studios.my-plan.webpay', [$studio, $payment]);
        } catch (PaymentRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);

            return back(fallback: route('studios.my-plan.buy', $studio));
        }
    }

    /**
     * The page on the way to Webpay, only for her own payment while it is
     * pending; once settled, «Mi plan» says how it went.
     */
    #[Authorize('self.buy_plan', 'studio')]
    public function show(Studio $studio, Payment $payment, #[CurrentUser] User $user): Response|RedirectResponse
    {
        abort_unless($payment->studio_id === $studio->id && $payment->user_id === $user->id && $payment->webpay_token !== null, 404);

        if ($payment->status !== PaymentStatus::Pending) {
            return to_route('studios.my-plan.index', $studio);
        }

        return Inertia::render('studios/webpay-redirect', [
            'url' => $payment->webpay_url,
            'token' => $payment->webpay_token,
            'amount' => $payment->amount,
        ]);
    }
}
