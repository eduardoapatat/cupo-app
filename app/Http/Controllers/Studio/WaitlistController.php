<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Bookings\LeaveWaitlist;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * A student waits for a spot in a full class, from «Reservar», and leaves
 * the line from «Reservar» or «Mis reservas».
 */
class WaitlistController extends Controller
{
    #[Authorize('self.book', 'studio')]
    public function store(Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, JoinWaitlist $joinWaitlist): RedirectResponse
    {
        try {
            $entry = $joinWaitlist->handle($classSession, $user, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You are on the waitlist, number :position. If a spot frees up in time, we book it for you.', [
                'position' => $entry->position(),
            ])]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.book.index', $studio));
    }

    #[Authorize('self.book', 'studio')]
    #[Authorize('leaveOwn', 'waitlistEntry')]
    public function destroy(Studio $studio, WaitlistEntry $waitlistEntry, LeaveWaitlist $leaveWaitlist): RedirectResponse
    {
        try {
            $leaveWaitlist->handle($waitlistEntry);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the waitlist.')]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.my-bookings.index', $studio));
    }
}
