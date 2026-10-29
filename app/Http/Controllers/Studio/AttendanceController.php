<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\MarkNoShow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\AttendanceRequest;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * Mark who missed a class, from «Mis clases», the Inicio or the Agenda.
 * The owner (bookings.override) may also correct it after it closes.
 */
class AttendanceController extends Controller
{
    #[Authorize('attendance.mark', 'studio')]
    #[Authorize('markAttendance', 'classSession')]
    public function update(AttendanceRequest $request, Studio $studio, ClassSession $classSession, Booking $booking, #[CurrentUser] User $user, MarkNoShow $markNoShow): RedirectResponse
    {
        try {
            $marked = $markNoShow->handle($booking, $request->noShow(), afterClosing: $user->can('bookings.override', $studio));

            Inertia::flash('toast', ['type' => 'success', 'message' => $request->noShow()
                ? __('We marked that :name missed the class.', ['name' => $marked->user->name])
                : __('We marked :name as present.', ['name' => $marked->user->name]),
            ]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.my-sessions.index', $studio));
    }
}
