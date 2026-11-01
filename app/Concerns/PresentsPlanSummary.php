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
     * last day of the plan that lasts the longest, and expiresSoon tells her
     * from the studio's warning days on (plan_expiry_warning_days).
     *
     * next is the plan she already bought that waits its turn, if any: then
     * expiresSoon is false, since she renewed.
     *
     * @return array{isUnlimited: bool, classesLeft: int|null, lastDay: string, daysLeft: int, expiresSoon: bool, next: array{name: string, startsOn: string}|null}|null
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
        $daysLeft = (int) $today->diffInDays($lastDay) + 1;
        $next = $student->planPurchases()->where('studio_id', $studio->id)->queued()->orderBy('starts_on')->first();

        return [
            'isUnlimited' => $isUnlimited,
            'classesLeft' => $isUnlimited ? null : (int) $valid->sum('credits_left'),
            'lastDay' => $lastDay->translatedFormat('l j \d\e F'),
            'daysLeft' => $daysLeft,
            'expiresSoon' => $next === null && $daysLeft <= $studio->plan_expiry_warning_days,
            'next' => $next === null ? null : [
                'name' => $next->name,
                'startsOn' => $next->starts_on->translatedFormat('l j \d\e F'),
            ],
        ];
    }
}
