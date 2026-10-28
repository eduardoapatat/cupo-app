<?php

namespace App\Actions\Bookings;

use App\Enums\BookingKind;
use App\Enums\ClassSessionStatus;
use App\Enums\WaitlistStatus;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;

/**
 * Give a freed spot to the first one waiting, while the session is still
 * before its waitlist cutoff. Later, the spot stays open for anyone.
 *
 * It must run inside the transaction that freed the spot, after updating
 * the session's row: that row lock makes concurrent cancellations promote
 * one after another, each with the spot it freed. See
 * docs/decisions/0007-lista-de-espera-en-la-misma-transaccion.md.
 */
class PromoteFromWaitlist
{
    /**
     * @return User|null Who got the spot, if anyone.
     */
    public function handle(ClassSession $session): ?User
    {
        $session = ClassSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

        if ($session->status !== ClassSessionStatus::Scheduled
            || $session->booked_count >= $session->capacity
            || now()->greaterThanOrEqualTo($session->waitlistClosesAt())) {
            return null;
        }

        foreach ($session->waitlistEntries()->waiting()->with('user')->get() as $entry) {
            if (! $this->canTakeTheSpot($session, $entry)) {
                $this->resolve($entry, WaitlistStatus::Left);

                continue;
            }

            ClassSession::query()->whereKey($session->id)->increment('booked_count');

            $booking = $session->bookings()->create([
                'user_id' => $entry->user_id,
                'kind' => BookingKind::Student,
                'booked_by_id' => $entry->added_by_id,
            ]);

            $this->resolve($entry, WaitlistStatus::Promoted, $booking->id);

            return $entry->user;
        }

        return null;
    }

    /**
     * Since she joined, she may have become the class's instructor or got a
     * place another way. Checked here, not left to the unique index: a
     * failed insert would abort the whole transaction in PostgreSQL.
     */
    private function canTakeTheSpot(ClassSession $session, WaitlistEntry $entry): bool
    {
        return $session->instructor_id !== $entry->user_id
            && ! $session->bookings()->current()->where('user_id', $entry->user_id)->exists();
    }

    private function resolve(WaitlistEntry $entry, WaitlistStatus $status, ?int $bookingId = null): void
    {
        WaitlistEntry::query()->whereKey($entry->id)->update([
            'status' => $status,
            'booking_id' => $bookingId,
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
