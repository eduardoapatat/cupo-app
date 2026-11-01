<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The money that came into a studio, as «Reportes» and the owner's Inicio
 * show it: approved payments, on the day they were paid (paid_at). A voided
 * payment no longer counts, even in the month it was paid.
 */
trait SumsIncome
{
    /**
     * A month's income so far, and the same stretch of the month before:
     * on November 15 at 14:00, from October 1 to October 15 at 14:00.
     *
     * @return array{total: int, previous: int, byMethod: array<int, array{method: string, label: string, total: int}>}
     */
    private function monthIncome(Studio $studio, CarbonImmutable $monthStart): array
    {
        $now = now(config()->string('studio.timezone'));
        $until = $now->lessThan($monthStart->addMonth()) ? $now : $monthStart->addMonth();
        $previousStart = $monthStart->subMonth();
        $previousUntil = $previousStart->add($monthStart->diff($until))->min($monthStart);

        $byMethod = $this->paidBetween($studio, $monthStart, $until)
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        return [
            'total' => (int) $byMethod->sum(),
            'previous' => (int) $this->paidBetween($studio, $previousStart, $previousUntil)->sum('amount'),
            'byMethod' => array_map(fn (PaymentMethod $method): array => [
                'method' => $method->value,
                'label' => $method->label(),
                'total' => (int) ($byMethod[$method->value] ?? 0),
            ], PaymentMethod::cases()),
        ];
    }

    /**
     * The studio's approved payments paid from one moment to another.
     *
     * @return Builder<Payment>
     */
    private function paidBetween(Studio $studio, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Payment::query()
            ->where('payments.studio_id', $studio->id)
            ->where('payments.status', PaymentStatus::Approved)
            ->where('payments.paid_at', '>=', $from->utc())
            ->where('payments.paid_at', '<', $to->utc());
    }
}
