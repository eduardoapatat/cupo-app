<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\MarkSessionAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\SessionAttendanceRequest;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;

/**
 * Save who came to a class, the whole list at once, from «Mis clases», the
 * Inicio or the Agenda. The owner (bookings.override) may also correct it
 * after it closes.
 */
class AttendanceController extends Controller
{
    #[Authorize('attendance.mark', 'studio')]
    #[Authorize('markAttendance', 'classSession')]
    public function update(SessionAttendanceRequest $request, Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, MarkSessionAttendance $markSessionAttendance): RedirectResponse
    {
        try {
            $saved = $markSessionAttendance->handle($classSession, $request->attendedIds(), afterClosing: $user->can('bookings.override', $studio));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('We saved the attendance: :attended of :total came.', $saved)]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.my-sessions.index', $studio));
    }
}
