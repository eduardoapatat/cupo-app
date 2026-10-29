<?php

namespace App\Actions\Bookings;

use App\Actions\Plans\ReturnClassToPlan;
use App\Enums\BookingStatus;
use App\Enums\CreditMovementReason;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Give up a place. It is on time while at least the studio's cancellation
 * window is left before the class, and the class goes back to her plan;
 * later, it is late and the class counts as used. The owner may return the class anyway (bookings.override, checked
 * by the caller), with a reason. The freed spot goes to the first one on
 * the waitlist while the class is before the waitlist cutoff.
 */
class CancelBooking
{
    public function __construct(
        private PromoteFromWaitlist $promoteFromWaitlist,
        private ReturnClassToPlan $returnClassToPlan,
    ) {}

    /**
     * @throws BookingRejected when the booking is no longer active or the class started.
     */
    public function handle(Booking $booking, User $by, bool $returnClass = false, ?string $reason = null): BookingCancellation
    {
        $session = $booking->classSession;

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new BookingRejected(__('This booking is no longer active.'));
        }

        if (now()->greaterThanOrEqualTo($session->starts_at)) {
            throw new BookingRejected(__('The class already started, so the booking can no longer be cancelled.'));
        }

        $status = $returnClass || now()->lessThanOrEqualTo($session->freeCancellationEndsAt())
            ? BookingStatus::CancelledOnTime
            : BookingStatus::CancelledLate;

        // On time because of the owner, after the window, is «returned».
        $returnReason = now()->lessThanOrEqualTo($session->freeCancellationEndsAt())
            ? CreditMovementReason::CancelledOnTime
            : CreditMovementReason::Returned;

        $promoted = DB::transaction(function () use ($booking, $session, $by, $status, $reason, $returnReason): ?User {
            // Conditional, so cancelling twice at once frees one spot only.
            $cancelled = Booking::query()
                ->whereKey($booking->id)
                ->where('status', BookingStatus::Confirmed)
                ->update([
                    'status' => $status,
                    'cancelled_at' => now(),
                    'cancelled_by_id' => $by->id,
                    'cancellation_reason' => $reason,
                    'updated_at' => now(),
                ]);

            if ($cancelled === 0) {
                throw new BookingRejected(__('This booking is no longer active.'));
            }

            ClassSession::query()->whereKey($session->id)->where('booked_count', '>', 0)->decrement('booked_count');

            if ($status === BookingStatus::CancelledOnTime) {
                $this->returnClassToPlan->handle($booking, $returnReason, $by);
            }

            return $this->promoteFromWaitlist->handle($session);
        });

        return new BookingCancellation($booking->refresh(), $promoted);
    }
}
