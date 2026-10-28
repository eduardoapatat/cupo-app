<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsAttendees;
use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis clases»: an instructor sees the classes she teaches, from yesterday
 * (to mark who missed it while the attendance is open) until the end of the
 * booking horizon, and who comes to each. Only her own: the query filters
 * by instructor. Names only, no contact data.
 */
class MySessionsController extends Controller
{
    use PresentsAttendees;

    #[Authorize('sessions.view', 'studio')]
    public function index(Studio $studio, #[CurrentUser] User $user): Response
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        $sessions = $studio->classSessions()
            ->where('instructor_id', $user->id)
            ->where('starts_at', '>=', $today->subDay()->utc())
            ->where('starts_at', '<', $today->addDays($studio->booking_horizon_days)->utc())
            ->with(['classType', 'studio', 'bookings' => fn ($bookings) => $bookings->current()->with('user')])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('studios/my-sessions', [
            'days' => $sessions
                ->groupBy(fn (ClassSession $session): string => $session->localStartsAt()->toDateString())
                ->map(fn ($daySessions, string $date): array => [
                    'date' => $date,
                    'label' => $daySessions->first()->localStartsAt()->translatedFormat('l j \d\e F'),
                    'isToday' => $date === $today->toDateString(),
                    'isYesterday' => $date === $today->subDay()->toDateString(),
                    'sessions' => $daySessions->map(fn (ClassSession $session): array => [
                        'id' => $session->id,
                        'startsAt' => $session->localStartsAt()->format('H:i'),
                        'endsAt' => $session->ends_at->timezone(config()->string('studio.timezone'))->format('H:i'),
                        'classTypeName' => $session->classType->name,
                        'bookedCount' => $session->booked_count,
                        'capacity' => $session->capacity,
                        'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
                        'hasStarted' => now()->greaterThanOrEqualTo($session->starts_at),
                        'attendanceIsOpen' => $session->attendanceIsOpen(),
                        'attendanceClosesLabel' => $session->attendanceClosesLabel(),
                        'attendees' => $this->presentAttendees($session),
                    ])->values(),
                ])
                ->values(),
        ]);
    }
}
