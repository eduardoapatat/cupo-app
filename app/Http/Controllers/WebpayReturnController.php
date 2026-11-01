<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ConfirmWebpayPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Where Webpay sends the student back after paying (return_url).
 *
 * It comes back by GET, with token_ws when she paid or was declined, with
 * TBK_TOKEN when she cancelled, and with only TBK_ORDEN_COMPRA when the
 * form timed out. In the integration environment a cancellation comes back
 * by POST: a cross-site POST carries no session cookie, so that route runs
 * without the web middleware (no session to overwrite hers, no CSRF) and
 * only redirects to the same GET, which carries her cookie.
 */
class WebpayReturnController extends Controller
{
    private const array PARAMETERS = ['token_ws', 'TBK_TOKEN', 'TBK_ORDEN_COMPRA', 'TBK_ID_SESION'];

    public function redirectToGet(Request $request): RedirectResponse
    {
        return redirect()->to(route('webpay.return', $request->only(self::PARAMETERS)), 303);
    }

    public function show(Request $request, #[CurrentUser] User $user, ConfirmWebpayPayment $confirmWebpayPayment): RedirectResponse
    {
        $token = $request->string('token_ws')->toString() ?: $request->string('TBK_TOKEN')->toString();
        $buyOrder = $request->string('TBK_ORDEN_COMPRA')->toString();
        $cancelled = ! $request->filled('token_ws');

        $hers = Payment::query()->where('user_id', $user->id)->where('method', PaymentMethod::Webpay);
        $payment = match (true) {
            $token !== '' => $hers->where('webpay_token', $token)->first(),
            $buyOrder !== '' => $hers->where('webpay_buy_order', $buyOrder)->first(),
            default => null,
        };

        abort_if($payment === null, 404);

        $payment = $confirmWebpayPayment->handle($payment, $cancelled);
        $studio = Studio::query()->findOrFail($payment->studio_id);

        Inertia::flash('toast', match ($payment->status) {
            PaymentStatus::Approved => ['type' => 'success', 'message' => __('You paid with Webpay. Your plan is active now.')],
            PaymentStatus::Pending => ['type' => 'info', 'message' => __('We are still confirming your payment with Webpay. Check again in a few minutes.')],
            default => ['type' => 'error', 'message' => $cancelled
                ? __('You cancelled the payment at Webpay: nothing was charged.')
                : __('Webpay declined the payment: nothing was charged.')],
        });

        return to_route('studios.my-plan.index', $studio);
    }
}
