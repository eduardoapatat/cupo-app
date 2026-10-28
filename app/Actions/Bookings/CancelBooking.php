<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Give up a place. It is on time while at least the studio's cancellation
 * window is left before the class; later, it is late and the class counts
 * as used. The owner may return the class anyway (bookings.override, checked
 * by the caller), with a reason. The freed spot goes to the first one on
 * the waitlist while the class is before the waitlist cutoff.
 */
class CancelBooking
{
    public function __construct(private PromoteFromWaitlist $promoteFromWaitlist) {}

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

        $promoted = DB::transaction(function () use ($booking, $session, $by, $status, $reason): ?User {
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

            return $this->promoteFromWaitlist->handle($session);
        });

        return new BookingCancellation($booking->refresh(), $promoted);
    }
}
