<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Webpay\WebpayClient;
use App\Webpay\WebpayException;
use Illuminate\Support\Str;

/**
 * A student starts paying a plan with Webpay: the payment waits, pending,
 * with the plan's price (never one from the browser) and a transaction
 * that Transbank created for it. Her browser goes to pay it, and
 * ConfirmWebpayPayment settles it when she comes back.
 */
class StartWebpayPayment
{
    public function __construct(private WebpayClient $webpay) {}

    /**
     * @throws PaymentRejected when the plan is not on sale or Webpay does not answer.
     */
    public function handle(Plan $plan, User $student): Payment
    {
        if ($plan->isArchived()) {
            throw new PaymentRejected(__('This plan is no longer on sale.'));
        }

        $payment = new Payment([
            'studio_id' => $plan->studio_id,
            'plan_id' => $plan->id,
            'user_id' => $student->id,
            'amount' => $plan->price,
            'method' => PaymentMethod::Webpay,
            // Up to 26 characters, unique: the day and a random part.
            'webpay_buy_order' => 'cupo'.now()->format('ymd').Str::upper(Str::random(12)),
        ]);
        $payment->forceFill(['status' => PaymentStatus::Pending])->save();

        try {
            $transaction = $this->webpay->create($payment->webpay_buy_order ?? '', (string) $student->id, $payment->amount, route('webpay.return'));
        } catch (WebpayException) {
            $payment->forceFill(['status' => PaymentStatus::Failed, 'status_reason' => __('Webpay did not answer.')])->save();

            throw new PaymentRejected(__('Webpay is not answering right now. Try again in a moment: nothing was charged.'));
        }

        $payment->forceFill(['webpay_token' => $transaction['token'], 'webpay_url' => $transaction['url']])->save();

        return $payment;
    }
}
