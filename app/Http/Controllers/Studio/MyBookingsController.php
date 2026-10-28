<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\CancelBooking;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis reservas»: the student's upcoming classes, which she can cancel, and
 * her history in the studio.
 */
class MyBookingsController extends Controller
{
    #[Authorize('self.book', 'studio')]
    public function index(Studio $studio, #[CurrentUser] User $user): Response
    {
        $inStudio = fn (Builder $session) => $session->where('studio_id', $studio->id);

        $upcoming = $user->bookings()
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('classSession', fn (Builder $session) => $inStudio($session)->where('starts_at', '>', now()))
            ->with('classSession.classType', 'classSession.instructor', 'classSession.studio')
            ->get()
            ->sortBy(fn (Booking $booking) => $booking->classSession->starts_at)
            ->values();

        $history = $user->bookings()
            ->whereHas('classSession', $inStudio)
            ->whereNotIn('bookings.id', $upcoming->modelKeys())
            ->join('class_sessions', 'class_sessions.id', '=', 'bookings.class_session_id')
            ->orderByDesc('class_sessions.starts_at')
            ->select('bookings.*')
            ->with('classSession.classType', 'classSession.instructor')
            ->paginate(20);

        return Inertia::render('studios/my-bookings', [
            'upcoming' => $upcoming->map(fn (Booking $booking): array => [
                ...$this->present($booking),
                'freeCancellationUntil' => BookingController::deadlineLabel($booking->classSession),
                'cancelsOnTime' => now()->lessThanOrEqualTo($booking->classSession->freeCancellationEndsAt()),
            ]),
            'history' => $history->through(fn (Booking $booking): array => [
                ...$this->present($booking),
                'status' => $this->cancelledWithTheClass($booking)
                    ? 'El estudio canceló la clase'
                    : $booking->status->label(),
            ]),
        ]);
    }

    #[Authorize('self.book', 'studio')]
    #[Authorize('cancelOwn', 'booking')]
    public function destroy(Studio $studio, Booking $booking, #[CurrentUser] User $user, CancelBooking $cancelBooking): RedirectResponse
    {
        try {
            $cancelled = $cancelBooking->handle($booking, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => $cancelled->status === BookingStatus::CancelledOnTime
                ? __('You cancelled your class. You keep it to use another day.')
                : __('You cancelled your class. It was after the free cancellation window, so the class counts as used.'),
            ]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.my-bookings.index', $studio));
    }

    /**
     * Whether the booking was cancelled because the studio cancelled the
     * class (same person, same moment, see CancelSession), and not by the
     * student before that.
     */
    private function cancelledWithTheClass(Booking $booking): bool
    {
        $session = $booking->classSession;

        return $session->status === ClassSessionStatus::Cancelled
            && $booking->cancelled_by_id === $session->cancelled_by_id
            && $booking->cancelled_at !== null
            && $session->cancelled_at !== null
            && $booking->cancelled_at->equalTo($session->cancelled_at);
    }

    /**
     * @return array{id: int, date: string, startsAt: string, classTypeName: string, instructorName: string|null}
     */
    private function present(Booking $booking): array
    {
        $session = $booking->classSession;

        return [
            'id' => $booking->id,
            'date' => $session->localStartsAt()->translatedFormat('l j \d\e F'),
            'startsAt' => $session->localStartsAt()->format('H:i'),
            'classTypeName' => $session->classType->name,
            'instructorName' => $session->instructor?->name,
        ];
    }
}
