<?php

namespace App\Actions\Plans;

use App\Actions\Bookings\BookingRejected;
use App\Enums\CreditMovementReason;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Take the class of a booking from the student's plans: the one that
 * expires first among those valid on the class's day, with classes left
 * and under their daily limit.
 *
 * It runs inside the booking's transaction. Each candidate's row is locked
 * before checking it, and its classes go down with a conditional UPDATE,
 * so two bookings at once never take the same last class nor go past the
 * daily limit. See docs/decisions/0008-clases-del-plan-con-update-condicional.md.
 */
class TakeClassFromPlan
{
    /**
     * @throws BookingRejected when no plan can pay for the class.
     */
    public function handle(ClassSession $session, User $student, User $by): PlanPurchase
    {
        $candidates = $this->usablePurchases($session, $student);

        if ($candidates->isEmpty()) {
            throw new BookingRejected($this->noPlanMessage($session, $student, $by));
        }

        foreach ($candidates as $candidate) {
            $purchase = PlanPurchase::query()->whereKey($candidate->id)->lockForUpdate()->firstOrFail();

            if (! $this->isUnderDailyLimit($purchase, $session)) {
                continue;
            }

            if ($purchase->isUnlimited()) {
                return $purchase;
            }

            $taken = PlanPurchase::query()
                ->whereKey($purchase->id)
                ->where('credits_left', '>', 0)
                ->decrement('credits_left');

            if ($taken === 1) {
                return $purchase->refresh();
            }
        }

        throw new BookingRejected($this->exhaustedMessage($candidates, $student, $by));
    }

    /**
     * Check, without taking anything, that some plan could pay for the class,
     * for instance before joining its waitlist.
     *
     * @throws BookingRejected when none can.
     */
    public function ensureCanPay(ClassSession $session, User $student, User $by): void
    {
        if ($this->usablePurchases($session, $student)->isEmpty()) {
            throw new BookingRejected($this->noPlanMessage($session, $student, $by));
        }
    }

    /**
     * Note the class taken, once the booking exists. Unlimited plans have no
     * balance to follow.
     */
    public function record(PlanPurchase $purchase, Booking $booking, User $by): void
    {
        if ($purchase->isUnlimited()) {
            return;
        }

        CreditMovement::create([
            'plan_purchase_id' => $purchase->id,
            'booking_id' => $booking->id,
            'delta' => -1,
            'reason' => CreditMovementReason::Booking,
            'created_by_id' => $by->id,
            'created_at' => now(),
        ]);
    }

    /**
     * The student's purchases that could pay for the class, without locking
     * them: valid on its day and with classes left, the one that expires
     * first first. The daily limit is checked when taking the class.
     *
     * @return Collection<int, PlanPurchase>
     */
    public function usablePurchases(ClassSession $session, User $student): Collection
    {
        return $student->planPurchases()
            ->where('studio_id', $session->studio_id)
            ->where('starts_on', '<=', $session->localStartsAt()->toDateString())
            ->where('expires_at', '>', $session->starts_at)
            ->where(fn (Builder $query) => $query->whereNull('credits')->orWhere('credits_left', '>', 0))
            ->orderBy('expires_at')
            ->orderBy('id')
            ->get();
    }

    private function isUnderDailyLimit(PlanPurchase $purchase, ClassSession $session): bool
    {
        if ($purchase->daily_limit === null) {
            return true;
        }

        $day = $session->localStartsAt()->startOfDay();

        $taken = $purchase->bookings()
            ->current()
            ->whereHas('classSession', fn (Builder $other) => $other
                ->where('starts_at', '>=', $day->utc())
                ->where('starts_at', '<', $day->addDay()->utc()))
            ->count();

        return $taken < $purchase->daily_limit;
    }

    private function noPlanMessage(ClassSession $session, User $student, User $by): string
    {
        $day = $session->localStartsAt()->translatedFormat('l j');

        return $student->is($by)
            ? __('You have no valid plan with classes left for :day. Buy one at the counter.', ['day' => $day])
            : __(':name has no valid plan with classes left for :day.', ['name' => $student->name, 'day' => $day]);
    }

    /**
     * Every plan valid that day was either used up meanwhile or at its
     * daily limit.
     *
     * @param  Collection<int, PlanPurchase>  $candidates
     */
    private function exhaustedMessage(Collection $candidates, User $student, User $by): string
    {
        $limit = $candidates->max('daily_limit');

        if ($limit !== null) {
            return $student->is($by)
                ? trans_choice('Your plan allows :count class a day, and you already booked that day.|Your plan allows :count classes a day, and you already booked them that day.', $limit)
                : trans_choice(':name\'s plan allows :count class a day, and she already booked that day.|:name\'s plan allows :count classes a day, and she already booked them that day.', $limit, ['name' => $student->name]);
        }

        return $student->is($by)
            ? __('You have no classes left in your plan.')
            : __(':name has no classes left in her plan.', ['name' => $student->name]);
    }
}
