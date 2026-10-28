<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis clases»: an instructor sees the classes she teaches, from today
 * until the end of the booking horizon, and who comes to each. Only her
 * own: the query filters by instructor. Names only, no contact data.
 */
class MySessionsController extends Controller
{
    #[Authorize('sessions.view', 'studio')]
    public function index(Studio $studio, #[CurrentUser] User $user): Response
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        $sessions = $studio->classSessions()
            ->where('instructor_id', $user->id)
            ->where('starts_at', '>=', $today->utc())
            ->where('starts_at', '<', $today->addDays($studio->booking_horizon_days)->utc())
            ->with(['classType', 'bookings' => fn ($bookings) => $bookings->current()->with('user')])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('studios/my-sessions', [
            'days' => $sessions
                ->groupBy(fn (ClassSession $session): string => $session->localStartsAt()->toDateString())
                ->map(fn ($daySessions, string $date): array => [
                    'date' => $date,
                    'label' => $daySessions->first()->localStartsAt()->translatedFormat('l j \d\e F'),
                    'isToday' => $date === $today->toDateString(),
                    'sessions' => $daySessions->map(fn (ClassSession $session): array => [
                        'id' => $session->id,
                        'startsAt' => $session->localStartsAt()->format('H:i'),
                        'endsAt' => $session->ends_at->timezone(config()->string('studio.timezone'))->format('H:i'),
                        'classTypeName' => $session->classType->name,
                        'bookedCount' => $session->booked_count,
                        'capacity' => $session->capacity,
                        'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
                        'attendees' => $session->bookings
                            ->map(fn (Booking $booking): string => $booking->user->name)
                            ->sort(fn (string $a, string $b): int => strcoll(mb_strtolower($a), mb_strtolower($b)))
                            ->values(),
                    ])->values(),
                ])
                ->values(),
        ]);
    }
}
