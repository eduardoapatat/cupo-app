<?php

namespace App\Actions\Payments;

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Webpay\WebpayClient;
use App\Webpay\WebpayException;
use App\Webpay\WebpayResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settles a pending Webpay payment, once: when she comes back from paying,
 * and every few minutes for whoever did not come back (webpay:reconcile).
 * The payment is locked while Transbank confirms it, so two confirmations
 * at once commit it once and create one plan; a payment already settled is
 * returned as it is. It is approved only when Transbank authorized the
 * same amount for the same buy order; then her plan starts today.
 */
class ConfirmWebpayPayment
{
    public function __construct(
        private WebpayClient $webpay,
        private SellPlan $sellPlan,
    ) {}

    /**
     * @param  bool  $cancelled  She came back saying she cancelled at Webpay.
     */
    public function handle(Payment $payment, bool $cancelled = false): Payment
    {
        return DB::transaction(function () use ($payment, $cancelled): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending) {
                return $locked;
            }

            // A cancelled payment is not confirmed, as Transbank asks: its
            // status is only read, in case it was paid after all.
            $result = $cancelled ? $this->status($locked) : $this->result($locked);

            if ($result === null && ! $cancelled) {
                // Transbank does not answer: it stays pending, and the next
                // webpay:reconcile tries again.
                return $locked;
            }

            if ($result?->isAuthorized() && $result->amount === $locked->amount && $result->buyOrder === $locked->webpay_buy_order) {
                return $this->approve($locked, $result);
            }

            if ($result?->isAuthorized()) {
                Log::warning('Webpay authorized a payment that does not match it.', ['payment' => $locked->id, 'amount' => $result->amount, 'buy_order' => $result->buyOrder]);
            }

            $locked->forceFill([
                'status' => PaymentStatus::Failed,
                'status_reason' => match (true) {
                    $result?->isAuthorized() === true => __('The amount paid does not match the plan.'),
                    $cancelled => __('You cancelled the payment at Webpay.'),
                    $result->isUnpaid() => __('The payment was not completed.'),
                    default => __('Webpay declined the payment.'),
                },
            ])->save();

            return $locked;
        });
    }

    /**
     * Confirm it; if Transbank refuses (already confirmed, or never paid),
     * read how it stands instead. Null when Transbank does not answer.
     */
    private function result(Payment $payment): ?WebpayResult
    {
        $token = (string) $payment->webpay_token;

        if ($token === '') {
            return null;
        }

        try {
            return $this->webpay->commit($token);
        } catch (WebpayException) {
            return $this->status($payment);
        }
    }

    /**
     * How it stands at Transbank, or null when Transbank does not answer.
     */
    private function status(Payment $payment): ?WebpayResult
    {
        try {
            return $payment->webpay_token === null ? null : $this->webpay->status($payment->webpay_token);
        } catch (WebpayException) {
            return null;
        }
    }

    private function approve(Payment $payment, WebpayResult $result): Payment
    {
        $plan = Plan::query()->whereKey($payment->plan_id)->firstOrFail();
        $student = User::query()->whereKey($payment->user_id)->firstOrFail();
        $purchase = $this->sellPlan->newPurchase($plan, $student, null, $payment->amount, __('The price when she started paying.'));

        $payment->forceFill([
            'status' => PaymentStatus::Approved,
            'plan_purchase_id' => $purchase->id,
            'paid_at' => now(),
            'webpay_authorization_code' => $result->authorizationCode,
            'card_last_digits' => $result->cardLastDigits,
        ])->save();

        return $payment;
    }
}
