<?php

namespace App\Actions\Plans;

use App\Enums\CreditMovementReason;
use App\Models\Booking;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use App\Models\User;

/**
 * Give a booking's class back to its plan: when she cancels in time, when
 * the owner returns it, or when the studio cancels the class. It runs inside
 * the cancellation's transaction. A booking without a plan (the team, or one
 * made in phase 2) and an unlimited plan have nothing to return.
 */
class ReturnClassToPlan
{
    public function handle(Booking $booking, CreditMovementReason $reason, User $by): void
    {
        if ($booking->plan_purchase_id === null) {
            return;
        }

        $returned = PlanPurchase::query()
            ->whereKey($booking->plan_purchase_id)
            ->whereNotNull('credits')
            ->whereColumn('credits_left', '<', 'credits')
            ->increment('credits_left');

        if ($returned === 0) {
            return;
        }

        CreditMovement::create([
            'plan_purchase_id' => $booking->plan_purchase_id,
            'booking_id' => $booking->id,
            'delta' => 1,
            'reason' => $reason,
            'created_by_id' => $by->id,
            'created_at' => now(),
        ]);
    }
}
