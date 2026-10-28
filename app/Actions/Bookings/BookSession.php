<?php

namespace App\Actions\Bookings;

use App\Enums\BookingKind;
use App\Enums\ClassSessionStatus;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Take a place in a session, for a student or for someone of the team.
 *
 * The spot is taken with a conditional UPDATE of the session's counter
 * (booked_count < capacity) in the same transaction that creates the
 * booking, so concurrent requests never oversell: PostgreSQL runs them one
 * after another on that row, and the CHECK on booked_count backs it up.
 * See docs/decisions/0005-cupo-garantizado-con-contador.md.
 */
class BookSession
{
    /**
     * @throws BookingRejected when a booking rule says no or the session is full.
     */
    public function handle(ClassSession $session, User $person, BookingKind $kind, User $bookedBy): Booking
    {
        $this->ensureBookable($session, $person);

        try {
            return DB::transaction(function () use ($session, $person, $kind, $bookedBy): Booking {
                $taken = ClassSession::query()
                    ->whereKey($session->id)
                    ->where('status', ClassSessionStatus::Scheduled)
                    ->whereColumn('booked_count', '<', 'capacity')
                    ->increment('booked_count');

                if ($taken === 0) {
                    throw new BookingRejected($session->fresh()?->status === ClassSessionStatus::Cancelled
                        ? __('This class was cancelled.')
                        : __('This class is full.'));
                }

                return $session->bookings()->create([
                    'user_id' => $person->id,
                    'kind' => $kind,
                    'booked_by_id' => $bookedBy->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingRejected(__(':name already has this class booked.', ['name' => $person->name]));
        }
    }

    /**
     * The studio's rules, checked before touching the counter. The ones
     * that race (the last spot, a double booking) are settled by the
     * database inside the transaction.
     */
    private function ensureBookable(ClassSession $session, User $person): void
    {
        $studio = $session->studio;

        if ($session->status === ClassSessionStatus::Cancelled) {
            throw new BookingRejected(__('This class was cancelled.'));
        }

        if ($session->instructor_id === null) {
            throw new BookingRejected(__('This class has no instructor yet, so it cannot be booked.'));
        }

        if (now()->greaterThanOrEqualTo($session->bookingClosesAt())) {
            throw new BookingRejected(__('Bookings for this class are closed.'));
        }

        $horizonEnd = now()->timezone(config()->string('studio.timezone'))->startOfDay()->addDays($studio->booking_horizon_days);

        if ($session->starts_at->greaterThanOrEqualTo($horizonEnd)) {
            throw new BookingRejected(__('This class cannot be booked yet: bookings open :days days ahead.', ['days' => $studio->booking_horizon_days]));
        }

        if ($session->bookings()->current()->where('user_id', $person->id)->exists()) {
            throw new BookingRejected(__(':name already has this class booked.', ['name' => $person->name]));
        }
    }
}
