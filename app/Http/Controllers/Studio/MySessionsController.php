<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsAttendees;
use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis clases»: an instructor picks a day, from yesterday (to take the
 * list of a class while its attendance is open) until the end of the
 * booking horizon, then one of her classes that day, and takes its list.
 * Only her own: the query filters by instructor. Names only, no contact
 * data. The day goes in «?dia=» and the class in «?clase=».
 */
class MySessionsController extends Controller
{
    use PresentsAttendees;

    #[Authorize('sessions.view', 'studio')]
    public function index(Request $request, Studio $studio, #[CurrentUser] User $user): Response
    {
        $timezone = config()->string('studio.timezone');
        $today = now($timezone)->startOfDay();
        $first = $today->subDay();
        $end = $today->addDays($studio->booking_horizon_days);

        $sessions = $studio->classSessions()
            ->where('instructor_id', $user->id)
            ->where('starts_at', '>=', $first->utc())
            ->where('starts_at', '<', $end->utc())
            ->with(['classType', 'studio', 'bookings' => fn ($bookings) => $bookings->current()->with('user')])
            ->orderBy('starts_at')
            ->get();
        $byDate = $sessions->toBase()->groupBy(fn (ClassSession $session): string => $session->localStartsAt()->toDateString());

        $date = $this->chosenDate($request->string('dia')->toString(), $first, $end, $today, $byDate);
        $daySessions = $byDate->get($date, collect());
        $selected = $daySessions->firstWhere('id', $request->integer('clase'))
            ?? $daySessions->first(fn (ClassSession $session): bool => $this->state($session) === 'to_mark')
            ?? $daySessions->first();

        return Inertia::render('studios/my-sessions', [
            'days' => collect(range(0, (int) $first->diffInDays($end) - 1))
                ->map(fn (int $offset): array => $this->presentDay($first->addDays($offset), $today, $byDate))
                ->values(),
            'selectedDate' => $date,
            'dayLabel' => CarbonImmutable::parse($date, $timezone)->translatedFormat('l j \d\e F'),
            'sessions' => $daySessions->map(fn (ClassSession $session): array => [
                'id' => $session->id,
                'startsAt' => $session->localStartsAt()->format('H:i'),
                'endsAt' => $session->ends_at->timezone($timezone)->format('H:i'),
                'classTypeName' => $session->classType->name,
                'bookedCount' => $session->booked_count,
                'capacity' => $session->capacity,
                'state' => $this->state($session),
            ])->values(),
            'selected' => $selected === null ? null : [
                'id' => $selected->id,
                'startsAt' => $selected->localStartsAt()->format('H:i'),
                'endsAt' => $selected->ends_at->timezone($timezone)->format('H:i'),
                'classTypeName' => $selected->classType->name,
                'bookedCount' => $selected->booked_count,
                'capacity' => $selected->capacity,
                'state' => $this->state($selected),
                'attendanceIsOpen' => $selected->attendanceIsOpen(),
                'attendanceOpensAt' => $selected->attendanceOpensAt()->timezone($timezone)->format('H:i'),
                'attendanceClosesLabel' => $selected->attendanceClosesLabel(),
                'takenAt' => $selected->attendance_taken_at?->timezone($timezone)->translatedFormat('l j \a \l\a\s H:i'),
                'attendees' => $this->presentAttendees($selected),
            ],
        ]);
    }

    /**
     * The asked day, if it is in range; otherwise yesterday when a list is
     * still to take there, so it is not forgotten, or else today.
     *
     * @param  Collection<array-key, Collection<int, ClassSession>>  $byDate
     */
    private function chosenDate(string $asked, CarbonImmutable $first, CarbonImmutable $end, CarbonImmutable $today, Collection $byDate): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $asked) === 1 && $asked >= $first->toDateString() && $asked < $end->toDateString()) {
            return $asked;
        }

        $yesterday = $first->toDateString();
        $pending = $byDate->get($yesterday, collect())->contains(fn (ClassSession $session): bool => $this->state($session) === 'to_mark');

        return $pending ? $yesterday : $today->toDateString();
    }

    /**
     * Where the list of a class stands, for its badge.
     *
     * @return 'cancelled'|'empty'|'taken'|'to_mark'|'upcoming'|'closed'
     */
    private function state(ClassSession $session): string
    {
        return match (true) {
            $session->status === ClassSessionStatus::Cancelled => 'cancelled',
            $session->bookings->isEmpty() => 'empty',
            $session->attendance_taken_at !== null => 'taken',
            $session->attendanceIsOpen() => 'to_mark',
            now()->lessThan($session->attendanceOpensAt()) => 'upcoming',
            default => 'closed',
        };
    }

    /**
     * A day of the strip, with the ids of its classes.
     *
     * @param  Collection<array-key, Collection<int, ClassSession>>  $byDate
     * @return array<string, mixed>
     */
    private function presentDay(CarbonImmutable $day, CarbonImmutable $today, Collection $byDate): array
    {
        return [
            'date' => $day->toDateString(),
            'weekday' => $day->isSameDay($today->subDay()) ? 'Ayer' : $day->translatedFormat('D'),
            'dayOfMonth' => $day->day,
            'longLabel' => $day->translatedFormat('l j \d\e F'),
            'isToday' => $day->isSameDay($today),
            'sessions' => $byDate->get($day->toDateString(), collect())->pluck('id')->values(),
        ];
    }
}
