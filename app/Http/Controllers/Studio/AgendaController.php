<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ClassSessionStatus;
use App\Enums\StudioRole;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Membership;
use App\Models\Studio;
use App\Models\User;
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
    #[Authorize('bookings.manage', 'studio')]
    public function index(Request $request, Studio $studio): Response
    {
        return Inertia::render('studios/agenda', [
            ...$this->weekProps($studio, $this->weekStart($request, null)),
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
            ...$this->weekProps($studio, $this->weekStart($request, $classSession)),
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
            ]),
        ]);
    }

    /**
     * The Monday of the week in ?semana=AAAA-MM-DD, of the class shown, or
     * of today.
     */
    private function weekStart(Request $request, ?ClassSession $session): CarbonImmutable
    {
        $timezone = config()->string('studio.timezone');
        $requested = $request->string('semana')->toString();

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) === 1
            ? CarbonImmutable::createFromFormat('!Y-m-d', $requested, $timezone)
            : null;

        return ($day ?: $session?->localStartsAt() ?? now($timezone))->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * @return array<string, mixed>
     */
    private function weekProps(Studio $studio, CarbonImmutable $start): array
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $sessions = $studio->classSessions()
            ->where('starts_at', '>=', $start->utc())
            ->where('starts_at', '<', $start->addWeek()->utc())
            ->with(['classType', 'instructor'])
            ->orderBy('starts_at')
            ->get();

        return [
            'week' => [
                'start' => $start->toDateString(),
                'label' => $start->translatedFormat('j \d\e F').' – '.$start->addDays(6)->translatedFormat('j \d\e F'),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $start->addWeek()->toDateString(),
                'isCurrent' => $start->isSameDay($today->startOfWeek(CarbonImmutable::MONDAY)),
            ],
            'days' => collect(range(0, 6))->map(function (int $offset) use ($start, $today, $sessions): array {
                $day = $start->addDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'label' => Str::ucfirst(rtrim($day->translatedFormat('D'), '.')).' '.$day->day,
                    'longLabel' => $day->translatedFormat('l j'),
                    'isToday' => $day->isSameDay($today),
                    'sessions' => $sessions
                        ->filter(fn (ClassSession $session): bool => $session->localStartsAt()->isSameDay($day))
                        ->map(fn (ClassSession $session): array => [
                            'id' => $session->id,
                            'startsAt' => $session->localStartsAt()->format('H:i'),
                            'classTypeName' => $session->classType->name,
                            'instructorName' => $session->instructor?->name,
                            'bookedCount' => $session->booked_count,
                            'capacity' => $session->capacity,
                            'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
                            'hasStarted' => now()->greaterThanOrEqualTo($session->starts_at),
                        ])
                        ->values(),
                ];
            }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSession(Studio $studio, ClassSession $session, User $user): array
    {
        $attendees = $session->bookings()->current()->with('user')->get()
            ->sortBy(fn (Booking $booking): string => mb_strtolower($booking->user->name))
            ->values();
        $taken = $attendees->pluck('user_id')->push($session->instructor_id)->filter()->all();
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
            'attendees' => $attendees->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'name' => $booking->user->name,
                'isStaff' => $booking->kind->value === 'staff',
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
            ],
        ];
    }
}
