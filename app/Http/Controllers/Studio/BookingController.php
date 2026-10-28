<?php

namespace App\Http\Controllers\Studio;

use App\Actions\Bookings\BookingRejected;
use App\Actions\Bookings\BookSession;
use App\Enums\BookingKind;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Reservar»: a student books her classes, day by day, within the studio's
 * horizon. The rules live in BookSession; this page only shows them.
 */
class BookingController extends Controller
{
    #[Authorize('self.book', 'studio')]
    public function index(Request $request, Studio $studio, #[CurrentUser] User $user): Response
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();
        $days = collect(range(0, $studio->booking_horizon_days - 1))->map(fn (int $offset): CarbonImmutable => $today->addDays($offset));
        $selectedDay = $this->selectedDay($request, $days);

        $sessions = $studio->classSessions()
            ->upcoming()
            ->where('starts_at', '<', $today->addDays($studio->booking_horizon_days))
            ->with(['classType', 'instructor', 'studio'])
            ->orderBy('starts_at')
            ->get();
        $sessionsByDay = $sessions->groupBy(fn (ClassSession $session): string => $session->localStartsAt()->toDateString());

        /** @var Collection<int, int> $bookingIds */
        $bookingIds = $user->bookings()->current()->whereIn('class_session_id', $sessions->modelKeys())->pluck('id', 'class_session_id');

        return Inertia::render('studios/book', [
            'days' => $days->map(fn (CarbonImmutable $day, int $offset): array => [
                'date' => $day->toDateString(),
                'weekday' => $offset === 0 ? 'Hoy' : Str::ucfirst(rtrim($day->translatedFormat('D'), '.')),
                'dayOfMonth' => $day->day,
                'sessionCount' => $sessionsByDay->get($day->toDateString(), collect())->count(),
            ])->values(),
            'selectedDay' => [
                'date' => $selectedDay->toDateString(),
                'label' => $selectedDay->translatedFormat('l j \d\e F'),
            ],
            'sessions' => $sessionsByDay->get($selectedDay->toDateString(), collect())
                ->map(fn (ClassSession $session): array => $this->present($session, $bookingIds->get($session->id)))
                ->values(),
        ]);
    }

    #[Authorize('self.book', 'studio')]
    public function store(Studio $studio, ClassSession $classSession, #[CurrentUser] User $user, BookSession $bookSession): RedirectResponse
    {
        try {
            $bookSession->handle($classSession, $user, BookingKind::Student, $user);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You booked :class on :day at :time.', [
                'class' => $classSession->classType->name,
                'day' => $classSession->localStartsAt()->translatedFormat('l j'),
                'time' => $classSession->localStartsAt()->format('H:i'),
            ])]);
        } catch (BookingRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return back(fallback: route('studios.book.index', $studio));
    }

    /**
     * The day in ?dia=AAAA-MM-DD, or today when it is missing or outside
     * the horizon.
     *
     * @param  Collection<int, CarbonImmutable>  $days
     */
    private function selectedDay(Request $request, Collection $days): CarbonImmutable
    {
        $requested = $request->string('dia')->toString();

        return $days->first(fn (CarbonImmutable $day): bool => $day->toDateString() === $requested) ?? $days->first();
    }

    /**
     * A session as a card of «Reservar», with what the student can do.
     *
     * @return array<string, mixed>
     */
    private function present(ClassSession $session, ?int $bookingId): array
    {
        $state = match (true) {
            $bookingId !== null => 'booked',
            $session->instructor_id === null => 'no_instructor',
            now()->greaterThanOrEqualTo($session->bookingClosesAt()) => 'closed',
            $session->booked_count >= $session->capacity => 'full',
            default => 'open',
        };

        return [
            'id' => $session->id,
            'startsAt' => $session->localStartsAt()->format('H:i'),
            'durationMinutes' => (int) $session->starts_at->diffInMinutes($session->ends_at),
            'classTypeName' => $session->classType->name,
            'instructorName' => $session->instructor?->name,
            'spotsLeft' => max(0, $session->capacity - $session->booked_count),
            'state' => $state,
            'bookingId' => $bookingId,
            'freeCancellationUntil' => self::deadlineLabel($session),
            'cancelsOnTime' => now()->lessThanOrEqualTo($session->freeCancellationEndsAt()),
        ];
    }

    /**
     * Until when cancelling keeps the class, in words: «las 13:00», or «el
     * lunes 2 a las 19:00» when that falls on an earlier day.
     */
    public static function deadlineLabel(ClassSession $session): string
    {
        $deadline = $session->freeCancellationEndsAt()->timezone(config()->string('studio.timezone'));

        return $deadline->isSameDay($session->localStartsAt())
            ? 'las '.$deadline->format('H:i')
            : 'el '.$deadline->translatedFormat('l j \a \l\a\s H:i');
    }
}
