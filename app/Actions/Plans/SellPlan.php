<?php

namespace App\Actions\Plans;

use App\Actions\CashRegisters\CashRegisterRejected;
use App\Enums\PaymentMethod;
use App\Models\CashRegister;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sell a plan to a student at the counter: the purchase, with a copy of
 * the plan as it is today, and its payment into the open cash register of
 * whoever sells, together. It lasts validity_days counted from today, in
 * the studio's time zone: 30 days bought on October 29 last until November
 * 27 at 23:59.
 */
class SellPlan
{
    /**
     * @param  CashRegister  $register  The open register of whoever sells, today's (checked by the caller).
     * @param  int|null  $price  A price other than the plan's, or null for the list price (plans.discount, checked by the caller).
     * @param  string|null  $receiptPath  The transfer receipt, already stored, when she brought one.
     *
     * @throws CashRegisterRejected when the register was closed meanwhile.
     */
    public function handle(Plan $plan, User $student, CashRegister $register, PaymentMethod $method, ?int $price = null, ?string $priceReason = null, ?string $receiptPath = null): PlanPurchase
    {
        try {
            return $this->sell($plan, $student, $register, $method, $price, $priceReason, $receiptPath);
        } catch (QueryException $exception) {
            // The register was closed before the payment got in (the trigger
            // of payments_need_an_open_register).
            if ($exception->getCode() === '23514' && str_contains($exception->getMessage(), 'cash register')) {
                throw new CashRegisterRejected(__('Your cash register was closed, so nothing was charged.'));
            }

            throw $exception;
        }
    }

    /**
     * The purchase: a copy of the plan as it is today, lasting its days
     * from today. Also used when a transfer from the app is approved.
     */
    public function newPurchase(Plan $plan, User $student, ?int $soldById, int $price, ?string $priceReason = null): PlanPurchase
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return PlanPurchase::create([
            'studio_id' => $plan->studio_id,
            'user_id' => $student->id,
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'price' => $price,
            'list_price' => $plan->price,
            'credits' => $plan->credits,
            'credits_left' => $plan->credits,
            'validity_days' => $plan->validity_days,
            'daily_limit' => $plan->daily_limit,
            'starts_on' => $today->toDateString(),
            'expires_at' => $today->addDays($plan->validity_days)->utc(),
            'sold_by_id' => $soldById,
            'price_reason' => $price !== $plan->price ? $priceReason : null,
        ]);
    }

    private function sell(Plan $plan, User $student, CashRegister $register, PaymentMethod $method, ?int $price, ?string $priceReason, ?string $receiptPath): PlanPurchase
    {
        $amount = $price ?? $plan->price;

        return DB::transaction(function () use ($plan, $student, $register, $method, $amount, $priceReason, $receiptPath): PlanPurchase {
            $purchase = $this->newPurchase($plan, $student, $register->user_id, $amount, $priceReason);

            $purchase->payments()->create([
                'studio_id' => $plan->studio_id,
                'user_id' => $student->id,
                'cash_register_id' => $register->id,
                'amount' => $amount,
                'method' => $method,
                'received_by_id' => $register->user_id,
                'receipt_path' => $receiptPath,
            ]);

            return $purchase;
        });
    }
}
