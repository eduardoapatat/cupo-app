<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Bookings\LeaveWaitlist;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\AgendaWaitlistRequest;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * The front desk and the owner put a student in a full class's waitlist,
 * for instance one who called or does not use the app, and take her out.
 */
class AgendaWaitlistController extends Controller
{
    /**
     * Only students of the studio wait; anyone else answers 404.
     */
    #[Authorize('bookings.manage', 'studio')]
    public function store(AgendaWaitlistRequest $request, Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, JoinWaitlist $joinWaitlist): RedirectResponse
    {
        $membership = $studio->memberships()
            ->where('user_id', $request->integer('user_id'))
            ->where('role', StudioRole::Student)
            ->with('user')
            ->firstOrFail();

        try {
            $entry = $joinWaitlist->handle($classSession, $membership->user, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is on the waitlist, number :position.', [
                'name' => $membership->user->name,
                'position' => $entry->position(),
            ])]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }

    #[Authorize('bookings.manage', 'studio')]
    public function destroy(Studio $studio, ClassSession $classSession, WaitlistEntry $waitlistEntry, LeaveWaitlist $leaveWaitlist): RedirectResponse
    {
        try {
            $leaveWaitlist->handle($waitlistEntry);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We took :name off the waitlist.', ['name' => $waitlistEntry->user->name])]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }
}
