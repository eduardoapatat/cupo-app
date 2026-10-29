<?php

namespace App\Actions\Plans;

use App\Enums\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sell a plan to a student: the purchase, with a copy of the plan as it is
 * today, and its payment, together. It lasts validity_days counted from
 * today, in the studio's time zone: 30 days bought on October 29 last
 * until November 27 at 23:59.
 */
class SellPlan
{
    /**
     * @param  int|null  $price  A price other than the plan's, or null for the list price (plans.discount, checked by the caller).
     */
    public function handle(Plan $plan, User $student, User $seller, PaymentMethod $method, ?int $price = null, ?string $priceReason = null): PlanPurchase
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $amount = $price ?? $plan->price;

        return DB::transaction(function () use ($plan, $student, $seller, $method, $today, $amount, $price, $priceReason): PlanPurchase {
            $purchase = PlanPurchase::create([
                'studio_id' => $plan->studio_id,
                'user_id' => $student->id,
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'price' => $amount,
                'credits' => $plan->credits,
                'credits_left' => $plan->credits,
                'validity_days' => $plan->validity_days,
                'daily_limit' => $plan->daily_limit,
                'starts_on' => $today->toDateString(),
                'expires_at' => $today->addDays($plan->validity_days)->utc(),
                'sold_by_id' => $seller->id,
                'price_reason' => $price !== null && $price !== $plan->price ? $priceReason : null,
            ]);

            $purchase->payments()->create([
                'studio_id' => $plan->studio_id,
                'user_id' => $student->id,
                'amount' => $amount,
                'method' => $method,
                'received_by_id' => $seller->id,
            ]);

            return $purchase;
        });
    }
}
