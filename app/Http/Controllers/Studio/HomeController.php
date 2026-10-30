<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\PresentsAttendees;
use App\Concerns\PresentsPayments;
use App\Concerns\PresentsPlanSummary;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CashRegister;
use App\Models\ClassSession;
use App\Models\Payment;
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
    use PresentsPayments;
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
            'receiptsToReview' => $shows(HomeBlock::ReceiptsToReview) ? $this->receiptsToReview($studio) : null,
            'cashRegister' => $shows(HomeBlock::CashRegister) ? $this->cashRegister($studio, $user) : null,
            'alerts' => $shows(HomeBlock::Alerts) ? $this->alerts($studio) : null,
        ]);
    }

    /**
     * How many transfer receipts wait, and the oldest three, first come
     * first served.
     *
     * @return array{count: int, oldest: array<int, array<string, mixed>>}
     */
    private function receiptsToReview(Studio $studio): array
    {
        $toReview = $studio->payments()->where('status', PaymentStatus::ToReview);

        return [
            'count' => (clone $toReview)->count(),
            'oldest' => $toReview->with(['user', 'plan', 'planPurchase', 'cashRegister'])
                ->oldest('id')
                ->limit(3)
                ->get()
                ->map(fn (Payment $payment): array => $this->presentPayment($payment))
                ->all(),
        ];
    }

    /**
     * Her register: none yet today, open (with what it took in), closed, or
     * one of another day still open, to close first.
     *
     * @return array<string, mixed>
     */
    private function cashRegister(Studio $studio, User $user): array
    {
        $open = $user->cashRegisters()->where('studio_id', $studio->id)->open()->first();
        $register = $open ?? $user->cashRegisters()->where('studio_id', $studio->id)->today()->first();

        if ($register === null) {
            return ['state' => 'none'];
        }

        return [
            'state' => match (true) {
                $register->isClosed() => 'closed',
                $register->isToday() => 'open',
                default => 'stale',
            },
            'day' => $register->business_date->translatedFormat('l j \d\e F'),
            'openedAt' => $register->opened_at->timezone(config()->string('studio.timezone'))->format('H:i'),
            'totals' => $register->totals(),
            'difference' => $register->difference(),
        ];
    }

    /**
     * What the owner should look at: receipts waiting, registers left open
     * on another day, registers that did not match in the last week, and
     * today's voids.
     *
     * @return array<string, mixed>
     */
    private function alerts(Studio $studio): array
    {
        $timezone = config()->string('studio.timezone');
        $today = now($timezone)->toDateString();
        $weekAgo = now($timezone)->subDays(7)->toDateString();
        $present = fn (CashRegister $register): array => [
            'id' => $register->id,
            'personName' => $register->user->name ?? __('Former team member'),
            'day' => $register->business_date->translatedFormat('l j \d\e F'),
            'difference' => $register->difference(),
            'note' => $register->closing_note,
        ];

        return [
            'receiptsToReview' => $studio->payments()->where('status', PaymentStatus::ToReview)->count(),
            'openRegisters' => $studio->cashRegisters()->open()->whereDate('business_date', '<', $today)->with('user')->orderBy('business_date')->get()->map($present)->all(),
            'registersWithDifference' => $studio->cashRegisters()
                ->whereNotNull('closed_at')
                ->whereColumn('counted_cash', '!=', 'expected_cash')
                ->whereDate('business_date', '>=', $weekAgo)
                ->with('user')
                ->orderByDesc('business_date')
                ->get()
                ->map($present)
                ->all(),
            'voidedToday' => $studio->payments()
                ->where('status', PaymentStatus::Voided)
                ->where('voided_at', '>=', now($timezone)->startOfDay()->utc())
                ->with(['user', 'voidedBy'])
                ->latest('voided_at')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'studentName' => $payment->user->name,
                    'amount' => $payment->amount,
                    'voidedBy' => $payment->voidedBy?->name,
                    'reason' => $payment->status_reason,
                ])
                ->all(),
        ];
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
