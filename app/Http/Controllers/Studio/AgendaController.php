<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsAttendees;
use App\Enums\ClassSessionStatus;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Agenda»: the front desk and the owner see the week's classes with dates,
 * who comes to each, and act on one class from a side panel with its own
 * URL.
 */
class AgendaController extends Controller
{
    use PresentsAttendees;

    #[Authorize('bookings.manage', 'studio')]
    public function index(Request $request, Studio $studio): Response
    {
        return Inertia::render('studios/agenda', [
            ...$this->calendarProps($studio, $focus = $this->focusDay($request, null)),
            'view' => $view = $this->requestedView($request),
            'month' => $view === 'mes' ? $this->monthProps($studio, $focus) : null,
            'session' => null,
        ]);
    }

    /**
     * One class in the side panel, next to its week.
     */
    #[Authorize('bookings.manage', 'studio')]
    public function show(Request $request, Studio $studio, ClassSession $classSession, #[CurrentUser] User $user): Response
    {
        return Inertia::render('studios/agenda', [
            ...$this->calendarProps($studio, $focus = $this->focusDay($request, $classSession)),
            'view' => $view = $this->requestedView($request),
            'month' => $view === 'mes' ? $this->monthProps($studio, $focus) : null,
            'session' => $this->presentSession($studio, $classSession, $user),
        ]);
    }

    /**
     * The students that match a search, to book one of them in the class.
     */
    #[Authorize('bookings.manage', 'studio')]
    public function students(Request $request, Studio $studio, ClassSession $classSession): JsonResponse
    {
        $search = mb_substr(trim($request->string('buscar')->toString()), 0, 100);
        $booked = $classSession->bookings()->current()->pluck('user_id')->all();
        $waiting = $classSession->waitlistEntries()->waiting()->pluck('user_id')->all();

        $students = $studio->memberships()
            ->where('memberships.role', StudioRole::Student)
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->matchingSearch($search)))
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->orderByRaw('lower(users.name)')
            ->select('memberships.*')
            ->with('user')
            ->limit(10)
            ->get();

        return response()->json([
            'students' => $students->map(fn (Membership $membership): array => [
                'id' => $membership->user_id,
                'name' => $membership->user->name,
                'phone' => $membership->user->phone,
                'alreadyBooked' => in_array($membership->user_id, $booked, true),
                'alreadyWaiting' => in_array($membership->user_id, $waiting, true),
            ]),
        ]);
    }

    /**
     * The view in ?vista=dia|semana|mes, or null to let the screen choose:
     * the day on a phone, the week elsewhere.
     */
    private function requestedView(Request $request): ?string
    {
        $view = $request->string('vista')->toString();

        return in_array($view, ['dia', 'semana', 'mes'], true) ? $view : null;
    }

    /**
     * The day in focus: ?fecha=AAAA-MM-DD, the day of the class shown, or
     * today. The day view shows it; the week view, its week.
     */
    private function focusDay(Request $request, ?ClassSession $session): CarbonImmutable
    {
        $timezone = config()->string('studio.timezone');
        $requested = $request->string('fecha')->toString();

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) === 1
            ? CarbonImmutable::createFromFormat('!Y-m-d', $requested, $timezone)
            : null;

        return ($day ?: $session?->localStartsAt() ?? now($timezone))->startOfDay();
    }

    /**
     * The week around the day in focus, as the calendar draws it: each
     * class with its start in minutes after local midnight and its length.
     *
     * @return array<string, mixed>
     */
    private function calendarProps(Studio $studio, CarbonImmutable $focus): array
    {
        $timezone = config()->string('studio.timezone');
        $today = now($timezone)->startOfDay();
        $start = $focus->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);
        $sessions = $studio->classSessions()
            ->where('starts_at', '>=', $start->utc())
            ->where('starts_at', '<', $start->addWeek()->utc())
            ->with(['classType', 'instructor'])
            ->orderBy('starts_at')
            ->get();

        return [
            'today' => [
                'date' => $today->toDateString(),
                'weekday' => mb_strtoupper(rtrim($today->translatedFormat('D'), '.')),
                'dayOfMonth' => $today->day,
            ],
            'focus' => [
                'date' => $focus->toDateString(),
                'label' => $focus->translatedFormat('l j \d\e F'),
            ],
            'week' => [
                'start' => $start->toDateString(),
                'label' => rtrim($start->translatedFormat('j M'), '.').' – '.rtrim($end->translatedFormat('j M'), '.'),
                'monthLabel' => $this->monthLabel($start, $end),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $start->addWeek()->toDateString(),
                'sessionCount' => $sessions->where('status', ClassSessionStatus::Scheduled)->count(),
            ],
            'days' => collect(range(0, 6))->map(function (int $offset) use ($start, $today, $sessions): array {
                $day = $start->addDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'weekday' => Str::ucfirst(rtrim($day->translatedFormat('D'), '.')),
                    'dayOfMonth' => $day->day,
                    'longLabel' => $day->translatedFormat('l j'),
                    'isToday' => $day->isSameDay($today),
                    'sessions' => $sessions
                        ->filter(fn (ClassSession $session): bool => $session->localStartsAt()->isSameDay($day))
                        ->map(fn (ClassSession $session): array => $this->presentCard($session))
                        ->values(),
                ];
            }),
        ];
    }

    /**
     * The month around the day in focus, as whole weeks from Monday to
     * Sunday (5 or 6), with the classes of each day. One query covers
     * every shown day, including those of the months around it.
     *
     * @return array<string, mixed>
     */
    private function monthProps(Studio $studio, CarbonImmutable $focus): array
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $first = $focus->startOfMonth();
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $sessions = $studio->classSessions()
            ->where('starts_at', '>=', $gridStart->utc())
            ->where('starts_at', '<', $gridEnd->addDay()->utc())
            ->with(['classType', 'instructor'])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (ClassSession $session): string => $session->localStartsAt()->toDateString());
        $weekCount = (int) round($gridStart->diffInDays($gridEnd->addDay()) / 7);
        $cardsByDay = $sessions->map(fn ($daySessions): array => $daySessions
            ->map(fn (ClassSession $session): array => $this->presentCard($session))
            ->values()
            ->all());

        return [
            'start' => $first->toDateString(),
            'label' => Str::ucfirst($first->translatedFormat('F Y')),
            'rangeLabel' => '1 – '.rtrim($first->endOfMonth()->translatedFormat('j M'), '.'),
            'previous' => $first->subMonth()->toDateString(),
            'next' => $first->addMonth()->toDateString(),
            'sessionCount' => $sessions
                ->filter(fn ($daySessions, string $date): bool => str_starts_with($date, $first->format('Y-m')))
                ->flatten()
                ->where('status', ClassSessionStatus::Scheduled)
                ->count(),
            'weeks' => collect(range(0, $weekCount - 1))->map(fn (int $week): array => collect(range(0, 6))->map(function (int $offset) use ($gridStart, $week, $first, $today, $cardsByDay): array {
                $day = $gridStart->addDays($week * 7 + $offset);

                return [
                    'date' => $day->toDateString(),
                    'dayOfMonth' => $day->day,
                    'isToday' => $day->isSameDay($today),
                    'inMonth' => $day->isSameMonth($first),
                    'sessions' => $cardsByDay->get($day->toDateString(), []),
                ];
            })->all()),
        ];
    }

    /**
     * A class as the calendar draws it, in the week and in the month.
     *
     * @return array<string, mixed>
     */
    private function presentCard(ClassSession $session): array
    {
        $startsAt = $session->localStartsAt();

        return [
            'id' => $session->id,
            'startsAt' => $startsAt->format('H:i'),
            'endsAt' => $session->ends_at->timezone(config()->string('studio.timezone'))->format('H:i'),
            'startMinutes' => $startsAt->hour * 60 + $startsAt->minute,
            'durationMinutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
            'classTypeName' => $session->classType->name,
            'instructorName' => $session->instructor?->name,
            'bookedCount' => $session->booked_count,
            'capacity' => $session->capacity,
            'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
            'hasStarted' => now()->greaterThanOrEqualTo($session->starts_at),
        ];
    }

    /**
     * «Octubre 2026», or both months when the week crosses one:
     * «Octubre – noviembre 2026», «Diciembre 2026 – enero 2027».
     */
    private function monthLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->isSameMonth($end)) {
            return Str::ucfirst($start->translatedFormat('F Y'));
        }

        return $start->isSameYear($end)
            ? Str::ucfirst($start->translatedFormat('F')).' – '.$end->translatedFormat('F Y')
            : Str::ucfirst($start->translatedFormat('F Y')).' – '.$end->translatedFormat('F Y');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSession(Studio $studio, ClassSession $session, User $user): array
    {
        $taken = $session->bookings()->current()->pluck('user_id')->push($session->instructor_id)->filter()->all();
        $staff = $studio->memberships()
            ->whereIn('role', [StudioRole::Owner, StudioRole::FrontDesk, StudioRole::Instructor])
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $membership): string => mb_strtolower($membership->user->name));

        return [
            'id' => $session->id,
            'classTypeName' => $session->classType->name,
            'date' => $session->localStartsAt()->translatedFormat('l j \d\e F'),
            'startsAt' => $session->localStartsAt()->format('H:i'),
            'endsAt' => $session->ends_at->timezone(config()->string('studio.timezone'))->format('H:i'),
            'instructorId' => $session->instructor_id === null ? null : (string) $session->instructor_id,
            'instructorName' => $session->instructor?->name,
            'bookedCount' => $session->booked_count,
            'capacity' => $session->capacity,
            'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
            'cancellationReason' => $session->cancellation_reason,
            'hasStarted' => now()->greaterThanOrEqualTo($session->starts_at),
            'cancelsOnTime' => now()->lessThanOrEqualTo($session->freeCancellationEndsAt()),
            'attendees' => $this->presentAttendees($session),
            'attendanceIsOpen' => $session->attendanceIsOpen(),
            // The owner corrects a closed attendance too, as MarkAttendance allows.
            'canCorrectClosedAttendance' => $session->status === ClassSessionStatus::Scheduled
                && now()->greaterThanOrEqualTo($session->starts_at)
                && $user->can('bookings.override', $studio),
            'attendanceClosesLabel' => $session->attendanceClosesLabel(),
            'waitlistIsOpen' => $session->status === ClassSessionStatus::Scheduled
                && $session->instructor_id !== null
                && now()->lessThan($session->waitlistClosesAt()),
            'waitlist' => $session->waitlistEntries()->waiting()->with('user')->get()
                ->map(fn (WaitlistEntry $entry, int $index): array => [
                    'id' => $entry->id,
                    'name' => $entry->user->name,
                    'position' => $index + 1,
                ]),
            'teamOptions' => $staff
                ->reject(fn (Membership $membership): bool => in_array($membership->user_id, $taken, true))
                ->map(fn (Membership $membership): array => ['value' => (string) $membership->user_id, 'label' => $membership->user->name])
                ->values(),
            'teachers' => $staff
                ->filter(fn (Membership $membership): bool => in_array($membership->role, [StudioRole::Owner, StudioRole::Instructor], true))
                ->map(fn (Membership $membership): array => ['value' => (string) $membership->user_id, 'label' => $membership->user->name])
                ->values(),
            'can' => [
                'returnClass' => $user->can('bookings.override', $studio),
                'substitute' => $user->can('sessions.substitute', $studio),
                'cancel' => $user->can('sessions.cancel', $studio),
                'markAttendance' => $user->can('markAttendance', $session),
            ],
        ];
    }
}
