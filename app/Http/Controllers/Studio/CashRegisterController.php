<?php

namespace App\Http\Controllers\Studio;

use App\Actions\CashRegisters\CashRegisterRejected;
use App\Actions\CashRegisters\CloseCashRegister;
use App\Actions\CashRegisters\OpenCashRegister;
use App\Concerns\PresentsPayments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\CloseCashRegisterRequest;
use App\Http\Requests\Studio\OpenCashRegisterRequest;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Caja»: the cash register of whoever runs the counter today. She opens
 * it with the cash in the drawer, sells into it and closes it counting the
 * cash. The owner (cash.review) also sees the registers of the whole team.
 */
class CashRegisterController extends Controller
{
    use PresentsPayments;

    /**
     * How many days back the team's registers go.
     */
    private const TEAM_DAYS = 14;

    #[Authorize('cash.operate', 'studio')]
    public function index(Studio $studio, #[CurrentUser] User $user): Response
    {
        $today = now(config()->string('studio.timezone'))->toDateString();
        $mine = fn (): Builder => CashRegister::query()->where('studio_id', $studio->id)->where('user_id', $user->id);

        // Her open register, even one left open on another day, or the one
        // she already closed today.
        $current = $mine()->open()->first() ?? $mine()->whereDate('business_date', $today)->first();
        $canReviewTeam = $user->can('cash.review', $studio);

        return Inertia::render('studios/cash-register', [
            'register' => $current === null ? null : [
                ...$this->present($current),
                'payments' => $current->payments()
                    ->with(['user', 'plan', 'planPurchase', 'cashRegister'])
                    ->latest('id')
                    ->get()
                    ->map(fn (Payment $payment): array => $this->presentPayment($payment)),
            ],
            'history' => $mine()
                ->whereNotNull('closed_at')
                ->when($current, fn (Builder $query, CashRegister $register) => $query->whereKeyNot($register->id))
                ->orderByDesc('business_date')
                ->limit(10)
                ->get()
                ->map(fn (CashRegister $register): array => $this->present($register)),
            'team' => $canReviewTeam ? $this->team($studio) : null,
        ]);
    }

    #[Authorize('cash.operate', 'studio')]
    public function store(OpenCashRegisterRequest $request, Studio $studio, #[CurrentUser] User $user, OpenCashRegister $openCashRegister): RedirectResponse
    {
        try {
            $openCashRegister->handle($studio, $user, $request->integer('opening_cash'));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You opened your cash register.')]);
        } catch (CashRegisterRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return to_route('studios.cash-register.index', $studio);
    }

    #[Authorize('cash.operate', 'studio')]
    #[Authorize('close', 'cashRegister')]
    public function close(CloseCashRegisterRequest $request, Studio $studio, CashRegister $cashRegister, CloseCashRegister $closeCashRegister): RedirectResponse
    {
        try {
            $closeCashRegister->handle($cashRegister, $request->integer('counted_cash'), $request->closingNote());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You closed the cash register.')]);
        } catch (CashRegisterRejected $rejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $rejected->getMessage()]);
        }

        return to_route('studios.cash-register.index', $studio);
    }

    /**
     * The registers of the whole team in the last days, and any still open.
     *
     * @return array<int, array<string, mixed>>
     */
    private function team(Studio $studio): array
    {
        $since = now(config()->string('studio.timezone'))->subDays(self::TEAM_DAYS - 1)->toDateString();

        return $studio->cashRegisters()
            ->with('user')
            ->where(fn (Builder $query) => $query->whereDate('business_date', '>=', $since)->orWhereNull('closed_at'))
            ->orderByDesc('business_date')
            ->orderBy('opened_at')
            ->get()
            ->map(fn (CashRegister $register): array => $this->present($register))
            ->all();
    }

    /**
     * A register as the screen shows it.
     *
     * @return array<string, mixed>
     */
    private function present(CashRegister $register): array
    {
        $timezone = config()->string('studio.timezone');

        return [
            'id' => $register->id,
            'personName' => $register->user->name ?? __('Former team member'),
            'day' => $register->business_date->translatedFormat('l j \d\e F'),
            'isToday' => $register->isToday(),
            'openedAt' => $register->opened_at->timezone($timezone)->format('H:i'),
            'closedAt' => $register->closed_at?->timezone($timezone)->format('H:i'),
            'isClosed' => $register->isClosed(),
            'openingCash' => $register->opening_cash,
            'totals' => $register->totals(),
            'expectedCash' => $register->expectedCash(),
            'countedCash' => $register->counted_cash,
            'difference' => $register->difference(),
            'closingNote' => $register->closing_note,
        ];
    }
}
