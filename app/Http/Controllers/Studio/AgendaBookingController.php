<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\AgendaBookingRequest;
use App\Http\Requests\Studio\CancelAgendaBookingRequest;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * The front desk and the owner book or cancel for someone else, within the
 * same rules a student has. Only the owner returns a class after the
 * cancellation window (bookings.override).
 */
class AgendaBookingController extends Controller
{
    /**
     * Book a student of the studio, or add someone of the team. Anyone else
     * answers 404.
     */
    #[Authorize('bookings.manage', 'studio')]
    public function store(AgendaBookingRequest $request, Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, BookSession $bookSession): RedirectResponse
    {
        $kind = $request->kind();
        $membership = $studio->memberships()
            ->where('user_id', $request->integer('user_id'))
            ->whereIn('role', $kind === BookingKind::Student ? [StudioRole::Student] : [StudioRole::Owner, StudioRole::FrontDesk, StudioRole::Instructor])
            ->with('user')
            ->firstOrFail();

        try {
            $bookSession->handle($classSession, $membership->user, $kind, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now in the class.', ['name' => $membership->user->name])]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }

    #[Authorize('bookings.manage', 'studio')]
    public function destroy(CancelAgendaBookingRequest $request, Studio $studio, ClassSession $classSession, Booking $booking, #[CurrentUser] User $user, CancelBooking $cancelBooking): RedirectResponse
    {
        abort_if($request->returnsClass() && ! $user->can('bookings.override', $studio), 403);

        try {
            $cancellation = $cancelBooking->handle($booking, $user, $request->returnsClass(), $request->reason());
            $name = $cancellation->booking->user->name;
            $message = $cancellation->booking->status === BookingStatus::CancelledOnTime
                ? __('We cancelled :name\'s booking on time.', ['name' => $name])
                : __('We cancelled :name\'s booking. It was after the free cancellation window, so the class counts as used.', ['name' => $name]);

            if ($cancellation->promoted !== null) {
                $message .= ' '.__(':name took the spot from the waitlist.', ['name' => $cancellation->promoted->name]);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.agenda.show', [$studio, $classSession]));
    }
}
