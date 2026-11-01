<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Payments\PaymentRejected;
use App\Actions\Payments\SubmitTransferReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\BuyPlanRequest;
use App\Models\Plan;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Comprar plan»: the student pays a plan with Webpay, and her plan starts
 * once Transbank approves it (WebpayPaymentController); or, if the studio
 * wrote its transfer details, by bank transfer, uploading the receipt, and
 * her plan starts when the studio approves it.
 */
class BuyPlanController extends Controller
{
    #[Authorize('self.buy_plan', 'studio')]
    public function create(Studio $studio): Response
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return Inertia::render('studios/buy-plan', [
            'plans' => $studio->plans()->active()->orderBy('price')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'credits' => $plan->credits,
                'validityDays' => $plan->validity_days,
                'dailyLimit' => $plan->daily_limit,
                'lastDay' => $today->addDays($plan->validity_days - 1)->translatedFormat('l j \d\e F'),
            ]),
            'transferDetails' => $studio->transfer_details,
        ]);
    }

    #[Authorize('self.buy_plan', 'studio')]
    public function store(BuyPlanRequest $request, Studio $studio, #[CurrentUser] User $user, SubmitTransferReceipt $submitTransferReceipt): RedirectResponse
    {
        $plan = $studio->plans()->findOrFail($request->integer('plan_id'));

        try {
            $submitTransferReceipt->handle($plan, $user, $request->receipt());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We got your receipt. Your plan starts once the studio checks it.')]);

            return to_route('studios.my-plan.index', $studio);
        } catch (PaymentRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);

            return back(fallback: route('studios.my-plan.buy', $studio));
        }
    }
}
