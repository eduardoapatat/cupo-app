<?php

namespace App\Actions\Bookings;

use App\Enums\ClassSessionStatus;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Wait for a spot in a full session, for oneself or, from the Agenda, for
 * a student. When a spot frees up before the waitlist cutoff, CancelBooking
 * books it for the first one waiting.
 */
class JoinWaitlist
{
    /**
     * @throws BookingRejected when a rule says no or the session has a free spot.
     */
    public function handle(ClassSession $session, User $person, User $addedBy): WaitlistEntry
    {
        $this->ensureCanWait($session, $person);

        try {
            return DB::transaction(function () use ($session, $person, $addedBy): WaitlistEntry {
                // Locked, so a cancellation cannot free a spot between this
                // check and the insert and leave someone waiting beside it.
                $locked = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === ClassSessionStatus::Cancelled) {
                    throw new BookingRejected(__('This class was cancelled.'));
                }

                if ($locked->booked_count < $locked->capacity) {
                    throw new BookingRejected(__('A spot freed up: book it instead.'));
                }

                return $session->waitlistEntries()->create([
                    'user_id' => $person->id,
                    'added_by_id' => $addedBy->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingRejected(__(':name is already on the waitlist.', ['name' => $person->name]));
        }
    }

    private function ensureCanWait(ClassSession $session, User $person): void
    {
        if ($session->status === ClassSessionStatus::Cancelled) {
            throw new BookingRejected(__('This class was cancelled.'));
        }

        if ($session->instructor_id === null) {
            throw new BookingRejected(__('This class has no instructor yet, so it cannot be booked.'));
        }

        if ($session->instructor_id === $person->id) {
            throw new BookingRejected(__(':name teaches this class and cannot take a spot in it.', ['name' => $person->name]));
        }

        if (now()->greaterThanOrEqualTo($session->waitlistClosesAt())) {
            throw new BookingRejected(__('The waitlist for this class is closed.'));
        }

        if ($session->bookings()->current()->where('user_id', $person->id)->exists()) {
            throw new BookingRejected(__(':name already has this class booked.', ['name' => $person->name]));
        }

        if ($session->waitlistEntries()->waiting()->where('user_id', $person->id)->exists()) {
            throw new BookingRejected(__(':name is already on the waitlist.', ['name' => $person->name]));
        }
    }
}
