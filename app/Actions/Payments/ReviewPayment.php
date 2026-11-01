<?php

namespace App\Actions\Payments;

use App\Actions\Plans\SellPlan;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The front desk or the owner reviews a transfer receipt from the app.
 * Approving it creates her plan, lasting from today, at the price she
 * paid; rejecting it says why, and she can send another one. The payment
 * is locked, so two people reviewing it at once never approve it twice.
 */
class ReviewPayment
{
    public function __construct(private SellPlan $sellPlan) {}

    /**
     * @throws PaymentRejected when someone already reviewed it.
     */
    public function approve(Payment $payment, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $by): Payment {
            $locked = $this->lockToReview($payment);
            $plan = Plan::query()->whereKey($locked->plan_id)->firstOrFail();
            $student = User::query()->whereKey($locked->user_id)->firstOrFail();

            $purchase = $this->sellPlan->newPurchase($plan, $student, $by->id, $locked->amount, __('The price when she sent the receipt.'));

            $locked->forceFill([
                'status' => PaymentStatus::Approved,
                'paid_at' => now(),
                'plan_purchase_id' => $purchase->id,
                'received_by_id' => $by->id,
                'reviewed_by_id' => $by->id,
                'reviewed_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /**
     * @throws PaymentRejected when someone already reviewed it.
     */
    public function reject(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason): Payment {
            $locked = $this->lockToReview($payment);

            $locked->forceFill([
                'status' => PaymentStatus::Rejected,
                'reviewed_by_id' => $by->id,
                'reviewed_at' => now(),
                'status_reason' => $reason,
            ])->save();

            return $locked;
        });
    }

    /**
     * The payment, locked, if it still waits; the second one to review it
     * waits here and then finds it reviewed.
     */
    private function lockToReview(Payment $payment): Payment
    {
        $locked = Payment::query()->whereKey($payment->id)->with('reviewedBy')->lockForUpdate()->firstOrFail();

        if ($locked->status !== PaymentStatus::ToReview) {
            throw new PaymentRejected(__(':name already reviewed it.', ['name' => $locked->reviewedBy->name ?? __('Someone')]));
        }

        return $locked;
    }
}
