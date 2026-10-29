<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsAttendees;
use App\Concerns\PresentsPlanSummary;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Studio;
use App\Models\User;
use App\Navigation\HomeBlock;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's Inicio: the blocks of the member's role, each with its data
 * (StudioRole::homeBlocks()). Only the data of those blocks is loaded. The
 * studio, the role and the menu come as shared props (HandleInertiaRequests).
 */
class HomeController extends Controller
{
    use PresentsAttendees;
    use PresentsPlanSummary;

    public function __invoke(Studio $studio, #[CurrentUser] User $user): Response
    {
        $blocks = $user->roleIn($studio)?->homeBlocks() ?? [];
        $shows = fn (HomeBlock ...$candidates): bool => array_any($candidates, fn (HomeBlock $block): bool => in_array($block, $blocks, true));
        $todaySessions = $shows(HomeBlock::TodayOccupancy, HomeBlock::TodaySessions, HomeBlock::Arrivals)
            ? $this->todaySessions($studio)
            : null;

        return Inertia::render('studios/home', [
            'blocks' => array_map(fn (HomeBlock $block): array => ['key' => $block->value, 'isBuilt' => $block->isBuilt()], $blocks),
            'todaySessions' => $todaySessions?->map(fn (ClassSession $session): array => $this->presentSession($session))->values(),
            'arrivals' => $shows(HomeBlock::Arrivals) && $todaySessions !== null ? $this->arrivals($todaySessions) : null,
            'teachingToday' => $shows(HomeBlock::TeachingToday) ? $this->teachingToday($studio, $user) : null,
            'nextBooking' => $shows(HomeBlock::NextBooking) ? $this->nextBooking($studio, $user) : null,
            'planSummary' => $shows(HomeBlock::ClassesLeft, HomeBlock::PlanExpiry) ? $this->planSummary($user, $studio) : null,
            'waitingCount' => $shows(HomeBlock::NextBooking)
                ? $user->waitlistEntries()->waiting()->whereIn('class_session_id', $studio->classSessions()->upcoming()->select('id'))->count()
                : null,
        ]);
    }

    /**
     * Today's classes of the studio, in order, cancelled ones included.
     *
     * @return Collection<int, ClassSession>
     */
    private function todaySessions(Studio $studio): Collection
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return $studio->classSessions()
            ->where('starts_at', '>=', $today->utc())
            ->where('starts_at', '<', $today->addDay()->utc())
            ->with(['classType', 'instructor', 'studio'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The class that is on now, or else the next one today, with who comes,
     * so the front desk knows who is arriving.
     *
     * @param  Collection<int, ClassSession>  $todaySessions
     * @return array<string, mixed>|null
     */
    private function arrivals(Collection $todaySessions): ?array
    {
        $session = $todaySessions
            ->where('status', ClassSessionStatus::Scheduled)
            ->first(fn (ClassSession $session): bool => now()->lessThan($session->ends_at));

        if ($session === null) {
            return null;
        }

        return [
            ...$this->presentSession($session),
            'isOn' => now()->greaterThanOrEqualTo($session->starts_at),
            'attendees' => $this->presentAttendees($session),
        ];
    }

    /**
     * The instructor's own classes today, as «Mis clases» shows them, so she
     * can mark who missed one right from here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function teachingToday(Studio $studio, User $user): array
    {
        return $this->todaySessions($studio)
            ->where('instructor_id', $user->id)
            ->map(fn (ClassSession $session): array => [
                ...$this->presentSession($session),
                'attendanceIsOpen' => $session->attendanceIsOpen(),
                'attendanceClosesLabel' => $session->attendanceClosesLabel(),
                'attendees' => $this->presentAttendees($session),
            ])
            ->values()
            ->all();
    }

    /**
     * The student's next booked class in this studio.
     *
     * @return array<string, mixed>|null
     */
    private function nextBooking(Studio $studio, User $user): ?array
    {
        $booking = $user->bookings()
            ->where('status', BookingStatus::Confirmed)
            ->whereIn('class_session_id', $studio->classSessions()->where('starts_at', '>', now())->select('id'))
            ->with('classSession.classType', 'classSession.instructor', 'classSession.studio')
            ->get()
            ->sortBy(fn (Booking $booking) => $booking->classSession->starts_at)
            ->first();

        if ($booking === null) {
            return null;
        }

        $session = $booking->classSession;

        return [
            ...$this->presentSession($session),
            'date' => $session->localStartsAt()->translatedFormat('l j \d\e F'),
            'isToday' => $session->localStartsAt()->isSameDay(now(config()->string('studio.timezone'))),
            'freeCancellationUntil' => $session->freeCancellationLabel(),
            'cancelsOnTime' => now()->lessThanOrEqualTo($session->freeCancellationEndsAt()),
        ];
    }

    /**
     * @return array{id: int, startsAt: string, endsAt: string, classTypeName: string, instructorName: string|null, bookedCount: int, capacity: int, isCancelled: bool, hasStarted: bool}
     */
    private function presentSession(ClassSession $session): array
    {
        return [
            'id' => $session->id,
            'startsAt' => $session->localStartsAt()->format('H:i'),
            'endsAt' => $session->ends_at->timezone(config()->string('studio.timezone'))->format('H:i'),
            'classTypeName' => $session->classType->name,
            'instructorName' => $session->instructor?->name,
            'bookedCount' => $session->booked_count,
            'capacity' => $session->capacity,
            'isCancelled' => $session->status === ClassSessionStatus::Cancelled,
            'hasStarted' => now()->greaterThanOrEqualTo($session->starts_at),
        ];
    }
}
