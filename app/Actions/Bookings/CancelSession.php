<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The studio cancels a class: its bookings are cancelled on time, so
 * nobody loses the class, and its spots are released. The slot of the
 * schedule is not generated again for that time.
 */
class CancelSession
{
    /**
     * @throws BookingRejected when the class was already cancelled or started.
     */
    public function handle(ClassSession $session, User $by, string $reason): void
    {
        if ($session->status === ClassSessionStatus::Cancelled) {
            throw new BookingRejected(__('This class was already cancelled.'));
        }

        if (now()->greaterThanOrEqualTo($session->starts_at)) {
            throw new BookingRejected(__('The class already started, so it can no longer be cancelled.'));
        }

        $now = now();

        DB::transaction(function () use ($session, $by, $reason, $now): void {
            // A query, not save(): the model may be stale, and save() would
            // skip a booked_count it believes is already 0. Updating the row
            // first also locks it, so a booking that races this one either
            // commits before (and is cancelled below) or finds the class
            // cancelled.
            $cancelled = ClassSession::query()
                ->whereKey($session->id)
                ->where('status', ClassSessionStatus::Scheduled)
                ->update([
                    'status' => ClassSessionStatus::Cancelled,
                    'cancelled_at' => $now,
                    'cancelled_by_id' => $by->id,
                    'cancellation_reason' => $reason,
                    'booked_count' => 0,
                    'updated_at' => $now,
                ]);

            if ($cancelled === 0) {
                throw new BookingRejected(__('This class was already cancelled.'));
            }

            // The same moment as the class, so «Mis reservas» can tell these
            // apart from a booking the student had cancelled before.
            $session->bookings()->where('status', BookingStatus::Confirmed)->update([
                'status' => BookingStatus::CancelledOnTime,
                'cancelled_at' => $now,
                'cancelled_by_id' => $by->id,
                'cancellation_reason' => __('The studio cancelled the class.'),
                'updated_at' => $now,
            ]);
        });

        $session->refresh();
    }
}
