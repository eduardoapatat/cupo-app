<?php

namespace App\Http\Controllers\Studio;

use App\Concerns\SumsIncome;
use App\Enums\BookingStatus;
use App\Enums\ClassSessionStatus;
use App\Enums\CreditMovementReason;
use App\Enums\PaymentStatus;
use App\Enums\StudioRole;
use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditMovement;
use App\Models\PlanPurchase;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Reportes»: how the owner's month went. The money that came in (by day,
 * by method and by plan), how full the classes were and who came, how many
 * students have a plan, and whose plan expires this week, to call them.
 * Everything is added up in PostgreSQL, in the studio's time zone.
 */
class ReportController extends Controller
{
    use SumsIncome;

    #[Authorize('reports.view', 'studio')]
    public function __invoke(Request $request, Studio $studio): Response
    {
        $timezone = config()->string('studio.timezone');
        $thisMonth = now($timezone)->startOfMonth();
        $monthStart = $this->requestedMonth($request->query('mes'), $thisMonth);
        $monthEnd = $monthStart->addMonth();
        $until = $monthStart->equalTo($thisMonth) ? now($timezone) : $monthEnd;

        return Inertia::render('studios/reports', [
            'month' => [
                'value' => $monthStart->format('Y-m'),
                'label' => $monthStart->translatedFormat('F \d\e Y'),
                'isCurrent' => $monthStart->equalTo($thisMonth),
            ],
            'months' => $this->months($studio, $thisMonth),
            'income' => [
                ...$this->monthIncome($studio, $monthStart),
                'byDay' => $this->incomeByDay($studio, $monthStart, $until),
                'byPlan' => $this->incomeByPlan($studio, $monthStart, $monthEnd),
                'discounts' => $this->discounts($studio, $monthStart, $monthEnd),
                'voids' => $this->voids($studio, $monthStart, $monthEnd),
            ],
            'classes' => $this->classes($studio, $monthStart, $until),
            'students' => $this->students($studio, $monthStart, $monthEnd),
            'expiringSoon' => $this->expiringSoon($studio),
        ]);
    }

    /**
     * The month in «?mes=2026-10», or this one when it is missing, malformed
     * or still to come.
     */
    private function requestedMonth(mixed $value, CarbonImmutable $thisMonth): CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return $thisMonth;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $value, $thisMonth->timezone);

        return $month === null || $month->greaterThan($thisMonth) ? $thisMonth : $month;
    }

    /**
     * The months to choose from, newest first: from the studio's first one,
     * up to a year back.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function months(Studio $studio, CarbonImmutable $thisMonth): array
    {
        $first = $studio->created_at === null
            ? $thisMonth
            : CarbonImmutable::parse($studio->created_at)->timezone($thisMonth->timezone)->startOfMonth()->max($thisMonth->subMonths(11));
        $months = [];

        for ($month = $thisMonth; $month->greaterThanOrEqualTo($first); $month = $month->subMonth()) {
            $months[] = ['value' => $month->format('Y-m'), 'label' => $month->translatedFormat('F \d\e Y')];
        }

        return $months;
    }

    /**
     * Each day of the month up to today, with what came in.
     *
     * @return array<int, array{date: string, label: string, total: int}>
     */
    private function incomeByDay(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $until): array
    {
        $timezone = $monthStart->timezone->getName();
        $totals = $this->paidBetween($studio, $monthStart, $until)
            ->selectRaw("to_char(paid_at AT TIME ZONE ?, 'YYYY-MM-DD') as day, sum(amount) as total", [$timezone])
            ->groupBy('day')
            ->pluck('total', 'day');
        $days = [];

        for ($day = $monthStart; $day->lessThan($until); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('j M'),
                'total' => (int) ($totals[$day->toDateString()] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * What each plan brought in, the best seller first.
     *
     * @return array<int, array{name: string, sold: int, total: int}>
     */
    private function incomeByPlan(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        return $this->paidBetween($studio, $monthStart, $monthEnd)
            ->join('plan_purchases', 'plan_purchases.id', '=', 'payments.plan_purchase_id')
            ->selectRaw('plan_purchases.name, count(*) as sold, sum(payments.amount) as total')
            ->groupBy('plan_purchases.name')
            ->orderByDesc('total')
            ->orderBy('plan_purchases.name')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'sold' => (int) $row->sold, 'total' => (int) $row->total])
            ->all();
    }

    /**
     * What the owner left off the list price, in the plans paid this month.
     *
     * @return array{count: int, total: int}
     */
    private function discounts(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $row = $this->paidBetween($studio, $monthStart, $monthEnd)
            ->join('plan_purchases', 'plan_purchases.id', '=', 'payments.plan_purchase_id')
            ->whereColumn('plan_purchases.list_price', '>', 'plan_purchases.price')
            ->selectRaw('count(*) as count, coalesce(sum(plan_purchases.list_price - plan_purchases.price), 0) as total')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->count ?? 0), 'total' => (int) ($row->total ?? 0)];
    }

    /**
     * The payments voided this month, whenever they were paid.
     *
     * @return array{count: int, total: int}
     */
    private function voids(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $row = $studio->payments()
            ->where('status', PaymentStatus::Voided)
            ->where('voided_at', '>=', $monthStart->utc())
            ->where('voided_at', '<', $monthEnd->utc())
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as total')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->count ?? 0), 'total' => (int) ($row->total ?? 0)];
    }

    /**
     * The classes of the month that already started and were not cancelled:
     * how full they were, by time slot from the fullest, and who came.
     *
     * @return array{count: int, occupancy: int|null, bySlot: array<int, array{label: string, sessions: int, occupancy: int}>, attendance: array{attended: int, noShow: int, cancelledLate: int, toMark: int}}
     */
    private function classes(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $until): array
    {
        $timezone = $monthStart->timezone->getName();
        $held = fn (): Builder => ClassSession::query()
            ->where('class_sessions.studio_id', $studio->id)
            ->where('class_sessions.status', ClassSessionStatus::Scheduled)
            ->where('class_sessions.starts_at', '>=', $monthStart->utc())
            ->where('class_sessions.starts_at', '<', $until->utc());

        $totals = $held()->selectRaw('count(*) as count, sum(booked_count) as booked, sum(capacity) as capacity')->toBase()->first();
        $capacity = (int) ($totals->capacity ?? 0);

        $bySlot = $held()
            ->join('class_types', 'class_types.id', '=', 'class_sessions.class_type_id')
            ->selectRaw(
                "extract(isodow from class_sessions.starts_at AT TIME ZONE ?)::int as weekday, to_char(class_sessions.starts_at AT TIME ZONE ?, 'HH24:MI') as time, class_types.name, count(*) as sessions, sum(class_sessions.booked_count) as booked, sum(class_sessions.capacity) as capacity",
                [$timezone, $timezone],
            )
            ->groupBy('weekday', 'time', 'class_types.name')
            ->orderByRaw('sum(class_sessions.booked_count)::numeric / sum(class_sessions.capacity) desc')
            ->orderBy('weekday')
            ->orderBy('time')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'label' => mb_substr(Weekday::from((int) $row->weekday)->label(), 0, 3).' '.$row->time.' · '.$row->name,
                'sessions' => (int) $row->sessions,
                'occupancy' => (int) round(100 * (int) $row->booked / max(1, (int) $row->capacity)),
            ])
            ->all();

        $attendance = Booking::query()
            ->whereIn('class_session_id', $held()->select('class_sessions.id'))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->toBase()
            ->pluck('count', 'status');

        return [
            'count' => (int) ($totals->count ?? 0),
            'occupancy' => $capacity === 0 ? null : (int) round(100 * (int) $totals->booked / $capacity),
            'bySlot' => $bySlot,
            'attendance' => [
                'attended' => (int) ($attendance[BookingStatus::Attended->value] ?? 0),
                'noShow' => (int) ($attendance[BookingStatus::NoShow->value] ?? 0),
                'cancelledLate' => (int) ($attendance[BookingStatus::CancelledLate->value] ?? 0),
                'toMark' => (int) ($attendance[BookingStatus::Confirmed->value] ?? 0),
            ],
        ];
    }

    /**
     * How many students have a plan today, how many bought their first one
     * this month, and how many classes expired unused this month.
     *
     * @return array{withPlan: int, firstPlan: int, expiredClasses: int}
     */
    private function students(Studio $studio, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $firstPlans = PlanPurchase::query()
            ->where('studio_id', $studio->id)
            ->whereNull('cancelled_at')
            ->selectRaw('user_id, min(created_at) as first_bought_at')
            ->groupBy('user_id');

        return [
            'withPlan' => PlanPurchase::query()->where('studio_id', $studio->id)->valid()->distinct()->count('user_id'),
            'firstPlan' => DB::query()
                ->fromSub($firstPlans, 'firsts')
                ->where('first_bought_at', '>=', $monthStart->utc())
                ->where('first_bought_at', '<', $monthEnd->utc())
                ->count(),
            'expiredClasses' => -(int) CreditMovement::query()
                ->where('reason', CreditMovementReason::Expired)
                ->where('created_at', '>=', $monthStart->utc())
                ->where('created_at', '<', $monthEnd->utc())
                ->whereIn('plan_purchase_id', PlanPurchase::query()->where('studio_id', $studio->id)->select('id'))
                ->sum('delta'),
        ];
    }

    /**
     * The students whose plan ends within the next 7 days (today included)
     * and who have not bought the next one yet, the soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function expiringSoon(Studio $studio): array
    {
        $today = now(config()->string('studio.timezone'))->startOfDay();

        return PlanPurchase::query()
            ->where('studio_id', $studio->id)
            ->valid()
            ->where('expires_at', '<=', $today->addDays(7)->utc())
            ->whereNotExists(fn ($later) => $later->from('plan_purchases as later')
                ->whereColumn('later.user_id', 'plan_purchases.user_id')
                ->whereColumn('later.studio_id', 'plan_purchases.studio_id')
                ->whereColumn('later.expires_at', '>', 'plan_purchases.expires_at')
                ->whereNull('later.cancelled_at'))
            ->with(['user.memberships' => fn ($memberships) => $memberships->where('studio_id', $studio->id)->where('role', StudioRole::Student)])
            ->orderBy('expires_at')
            ->get()
            ->map(fn (PlanPurchase $purchase): array => [
                'id' => $purchase->id,
                'studentName' => $purchase->user->name,
                'phone' => $purchase->user->phone,
                'membershipId' => $purchase->user->memberships->first()?->id,
                'planName' => $purchase->name,
                'lastDay' => $purchase->lastDay()->translatedFormat('l j \d\e F'),
                'creditsLeft' => $purchase->credits_left,
            ])
            ->all();
    }
}
