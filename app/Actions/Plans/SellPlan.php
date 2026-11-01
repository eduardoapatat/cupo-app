<?php

namespace App\Actions\Plans;

use App\Actions\CashRegisters\CashRegisterRejected;
use App\Enums\PaymentMethod;
use App\Models\CashRegister;
use App\Models\Plan;
use App\Models\PlanPurchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sell a plan to a student at the counter: the purchase, with a copy of
 * the plan as it is today, and its payment into the open cash register of
 * whoever sells, together. It lasts validity_days counted from the day it
 * starts, in the studio's time zone: 30 days from October 29 last until
 * November 27 at 23:59. It starts today, or waits until her current plan
 * ends (docs/decisions/0011-un-plan-a-la-vez.md).
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
     * The purchase: a copy of the plan as it is today, lasting its days from
     * the day it starts (nextStartFor). Also used when a transfer from the
     * app or a Webpay payment is approved, so every new plan follows the
     * same rule: one plan at a time, the next one waiting its turn.
     */
    public function newPurchase(Plan $plan, User $student, ?int $soldById, int $price, ?string $priceReason = null): PlanPurchase
    {
        return DB::transaction(function () use ($plan, $student, $soldById, $price, $priceReason): PlanPurchase {
            // One new plan at a time per student and studio: a second sale
            // at the same moment waits here, then queues after the first.
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [$plan->studio_id, $student->id]);
            $startsOn = $this->nextStartFor($plan->studio_id, $student);

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
                'starts_on' => $startsOn->toDateString(),
                'expires_at' => $startsOn->addDays($plan->validity_days)->utc(),
                'sold_by_id' => $soldById,
                'price_reason' => $price !== $plan->price ? $priceReason : null,
            ]);
        });
    }

    /**
     * The day a new plan for her would start, in the studio's time zone:
     * today, or the day after the last of her plans that still has classes
     * (one valid now, or one already waiting). A plan with no classes left
     * does not hold the new one back.
     */
    public function nextStartFor(int $studioId, User $student): CarbonImmutable
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $last = $student->planPurchases()
            ->where('studio_id', $studioId)
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->where(fn (Builder $query) => $query->whereNull('credits')->orWhere('credits_left', '>', 0))
            ->orderByDesc('expires_at')
            ->first();

        // expires_at is the start of the day after its last day: that day.
        return $last === null ? $today : $last->expires_at->timezone($today->timezone)->startOfDay()->max($today);
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
                'paid_at' => now(),
            ]);

            return $purchase;
        });
    }
}
