<?php

namespace App\Actions\Plans;

use App\Enums\CreditMovementReason;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use Illuminate\Support\Facades\DB;

/**
 * A plan that expired with classes left loses them: its balance goes to 0
 * and one movement says how many. The row is locked first, as when a class
 * is taken from it, and running it twice takes nothing the second time.
 */
class ExpireUnusedClasses
{
    /**
     * @return int How many classes expired
     */
    public function handle(PlanPurchase $purchase): int
    {
        return DB::transaction(function () use ($purchase): int {
            $locked = PlanPurchase::query()
                ->whereKey($purchase->id)
                ->whereNull('cancelled_at')
                ->where('expires_at', '<=', now())
                ->where('credits_left', '>', 0)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->credits_left === null) {
                return 0;
            }

            $expired = $locked->credits_left;
            $locked->forceFill(['credits_left' => 0])->save();

            CreditMovement::create([
                'plan_purchase_id' => $locked->id,
                'delta' => -$expired,
                'reason' => CreditMovementReason::Expired,
                'created_at' => now(),
            ]);

            return $expired;
        });
    }
}
