<?php

namespace App\Concerns;

use App\Models\PlanPurchase;
use App\Models\Studio;
use App\Models\User;

/**
 * What a student has left in the studio's plans that are valid now, as
 * «Reservar», «Mi plan» and her Inicio show it.
 */
trait PresentsPlanSummary
{
    /**
     * Null when she has no valid plan. Unlimited when any of her valid plans
     * is; otherwise the classes left of all of them together. lastDay is the
     * last day of the plan that lasts the longest.
     *
     * @return array{isUnlimited: bool, classesLeft: int|null, lastDay: string, daysLeft: int}|null
     */
    private function planSummary(User $student, Studio $studio): ?array
    {
        $valid = $student->planPurchases()->where('studio_id', $studio->id)->valid()->get();

        if ($valid->isEmpty()) {
            return null;
        }

        $isUnlimited = $valid->contains(fn (PlanPurchase $purchase): bool => $purchase->isUnlimited());
        $lastDay = $valid->map(fn (PlanPurchase $purchase) => $purchase->lastDay())->max();
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return [
            'isUnlimited' => $isUnlimited,
            'classesLeft' => $isUnlimited ? null : (int) $valid->sum('credits_left'),
            'lastDay' => $lastDay->translatedFormat('l j \d\e F'),
            'daysLeft' => (int) $today->diffInDays($lastDay) + 1,
        ];
    }
}
